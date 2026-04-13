<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Web-only sessions endpoint.
 *
 * Keeps the mobile /api/v1/sessions contract untouched, but provides extra fields needed by the website
 * (e.g. location_name) and allows filtering by location_id.
 */
class WebSessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $category = $request->query('category');
        $instructorID = $request->query('instructorID');
        $locationId = $request->query('location_id');

        $requestedPerPage = $request->query('per_page');
        $requestedPage = $request->query('page');
        $shouldPaginate = $requestedPerPage !== null || $requestedPage !== null;

        $scheduleTz = (string) config('sessions.schedule_timezone', config('app.timezone'));
        $upcomingCutoff = Carbon::now($scheduleTz);

        $query = AppointmentModel::query()
            ->with(['service.category', 'provider', 'bookings', 'location'])
            ->where('status', 'approved')
            ->where('booking_start', '>', $upcomingCutoff);

        if ($locationId !== null && $locationId !== '') {
            $query->where('location_id', (int) $locationId);
        }

        if ($date) {
            try {
                $dateCarbon = Carbon::parse($date, $scheduleTz);
                $startOfDay = $dateCarbon->copy()->startOfDay();
                $endOfDay = $dateCarbon->copy()->endOfDay();
                $query->whereBetween('booking_start', [$startOfDay, $endOfDay]);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }

        if ($category !== null && $category !== '') {
            // Keep category behavior consistent with mobile: Yoga / Reformer Pilates normalization.
            // MobileSessionController contains the full logic; for web we support the same two canonical filters.
            $c = strtolower(trim(str_replace('-', ' ', (string) $category)));
            $canonical = null;
            if (in_array($c, ['yoga'], true)) {
                $canonical = 'Yoga';
            } elseif (in_array($c, ['reformer', 'reformer pilates', 'reform pilates'], true)) {
                $canonical = 'Reformer Pilates';
            }

            if ($canonical !== null) {
                // Filter by category via service relation (fast path: service.category.slug/name keywords)
                $query->whereHas('service', function ($q) use ($canonical) {
                    if ($canonical === 'Reformer Pilates') {
                        $q->whereHas('category', function ($qc) {
                            $qc->where(function ($w) {
                                $w->whereRaw('LOWER(slug) IN (?, ?, ?)', ['reformer-pilates', 'pilates', 'reformer'])
                                  ->orWhereRaw('LOWER(name) LIKE ?', ['%reformer%'])
                                  ->orWhereRaw('LOWER(name) LIKE ?', ['%pilates%']);
                            });
                        });
                        return;
                    }

                    // Yoga (default): include explicit yoga category/name matches.
                    $q->whereHas('category', function ($qc) {
                        $qc->where(function ($w) {
                            $w->whereRaw('LOWER(slug) = ?', ['yoga'])
                              ->orWhereRaw('LOWER(name) LIKE ?', ['%yoga%']);
                        });
                    });
                });
            }
        }

        if ($instructorID) {
            $query->where('provider_id', (int) $instructorID);
        }

        $query->orderBy('booking_start');

        if ($shouldPaginate) {
            $perPage = max(1, min(100, (int) ($requestedPerPage ?: 15)));
            $appointments = $query->paginate($perPage);
            $appointmentCollection = $appointments->getCollection();
            $meta = [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ];
        } else {
            $appointmentCollection = $query->get();
            $total = $appointmentCollection->count();
            $meta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $total,
                'total' => $total,
            ];
        }

        $customerId = $request->user()?->id;

        $items = $appointmentCollection->map(function ($appointment) use ($customerId, $scheduleTz, $upcomingCutoff) {
            $service = $appointment->service;
            $provider = $appointment->provider;
            $location = $appointment->location;

            $reservedBookings = $appointment->bookings->whereIn('status', ['confirmed', 'pending']);
            $totalPersons = $reservedBookings->sum('party_size');
            $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
            $remainingSpots = max(0, $maxCapacity - $totalPersons);
            $isFull = $totalPersons >= $maxCapacity;

            $myBooking = $customerId
                ? $reservedBookings->where('customer_id', $customerId)->first()
                : null;

            $isBooked = $myBooking !== null;
            $minutesBeforeCancellation = (int) ($service?->time_before ?? 0);
            $canCancelUntil = $myBooking
                ? Carbon::parse($appointment->booking_start, $scheduleTz)->subMinutes($minutesBeforeCancellation)
                : null;
            $canCancel = $isBooked && $canCancelUntil && Carbon::now($scheduleTz)->lte($canCancelUntil);
            $startInstant = $appointment->booking_start instanceof Carbon
                ? $appointment->booking_start->copy()
                : Carbon::parse($appointment->booking_start, $scheduleTz);
            $canBook = ! $isFull && $startInstant->gt($upcomingCutoff);

            // Reuse mobile field names for maximum compatibility with existing mapping
            return [
                'id' => (string) $appointment->id,
                'bookingId' => $myBooking ? (string) $myBooking->id : null,
                'instructor' => $provider ? $provider->name : '',
                'service' => $service ? $service->name : '',
                'serviceType' => $service instanceof ServiceModel ? ($service->category?->name ?? '') : '',
                'price' => $service ? (float) ($service->price ?? 0) : 0.0,
                'date' => ApiDateTime::toBusinessIso8601($appointment->booking_start),
                'dateUtc' => ApiDateTime::toUtcIso8601($appointment->booking_start),
                'isBooked' => $isBooked,
                'isFull' => $isFull,
                'remainingSpots' => $remainingSpots,
                'canCancel' => $canCancel,
                'canBook' => $canBook,
                'minutesBeforeCancellation' => $minutesBeforeCancellation,
                // Web additions:
                'location_id' => $appointment->location_id ? (int) $appointment->location_id : null,
                'location_name' => $location ? (string) ($location->name ?? '') : '',
            ];
        });

        return response()->json([
            'data' => $items->values()->toArray(),
            'meta' => $meta,
        ]);
    }
}

