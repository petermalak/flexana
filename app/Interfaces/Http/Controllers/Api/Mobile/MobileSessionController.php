<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MobileSessionController extends Controller
{
    /**
     * Get sessions (appointments) with optional pagination.
     * Query params: date, category (Yoga | Reformer Pilates), instructorID, per_page (default 15), page
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $category = $request->query('category');
        $instructorID = $request->query('instructorID');
        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));

        $query = AppointmentModel::query()
            ->with(['service', 'provider', 'bookings'])
            ->where('status', 'approved');

        if ($date) {
            try {
                $dateCarbon = Carbon::parse($date);
                // Use whereBetween to handle timezone correctly - filter for the entire day
                $startOfDay = $dateCarbon->copy()->startOfDay();
                $endOfDay = $dateCarbon->copy()->endOfDay();
                $query->whereBetween('booking_start', [$startOfDay, $endOfDay]);
            } catch (\Exception $e) {
                // Invalid date format - ignore the filter
                Log::warning('Invalid date filter in sessions API: ' . $date);
            }
        }
        if ($category !== null && $category !== '') {
            // Category is determined by service name only (no category_id in DB)
            $query->whereHas('service', function ($q) use ($category) {
                $name = strtolower(trim($category));
                if ($name === 'yoga') {
                    $q->where('name', 'LIKE', '%Yoga%');
                } elseif ($name === 'reformer pilates') {
                    $q->where(function ($sub) {
                        $sub->where('name', 'LIKE', '%Reformer Pilates%')
                            ->orWhere('name', 'LIKE', '%Reform Pilates%');
                    });
                } else {
                    $q->where('name', 'LIKE', '%' . addcslashes($category, '%_\\') . '%');
                }
            });
        }
        if ($instructorID) {
            $query->where('provider_id', $instructorID);
        }

        $query->orderBy('booking_start');

        $appointments = $query->paginate($perPage);
        $customerId = $request->user()?->id;

        $items = $appointments->getCollection()->map(function ($appointment) use ($customerId) {
            $service = $appointment->service;
            $provider = $appointment->provider;
            $approvedBookings = $appointment->bookings->where('status', 'confirmed');
            $totalPersons = $approvedBookings->sum('party_size');
            $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
            $isFull = $totalPersons >= $maxCapacity;
            $myBooking = $customerId
                ? $approvedBookings->where('customer_id', $customerId)->first()
                : null;
            $isBooked = $myBooking !== null;
            $minutesBeforeCancellation = (int) ($service->time_before ?? 0);
            $canCancelUntil = $myBooking
                ? Carbon::parse($appointment->booking_start)->subMinutes($minutesBeforeCancellation)
                : null;
            $canCancel = $isBooked && $canCancelUntil && Carbon::now()->lte($canCancelUntil);
            $canBook = ! $isFull && Carbon::parse($appointment->booking_start)->gt(Carbon::now());

            // Category (Yoga / Reformer Pilates) — not class format (Private/Group)
            $serviceType = null;
            if ($service && $service->name) {
                $name = strtolower($service->name);
                if (str_contains($name, 'reformer') || str_contains($name, 'reform pilates')) {
                    $serviceType = 'Reformer Pilates';
                } elseif (str_contains($name, 'yoga')) {
                    $serviceType = 'Yoga';
                } else {
                    $serviceType = 'Yoga';
                }
            }

            return [
                'id' => (string) $appointment->id,
                'instructor' => $provider ? $provider->name : '',
                'service' => $service ? $service->name : '',
                'serviceType' => $serviceType,
                'date' => Carbon::parse($appointment->booking_start)->toIso8601String(),
                'isBooked' => $isBooked,
                'isFull' => $isFull,
                'canCancel' => $canCancel,
                'canBook' => $canBook,
                'minutesBeforeCancellation' => $minutesBeforeCancellation,
            ];
        });

        return response()->json([
            'data' => $items->values()->toArray(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
    }
}
