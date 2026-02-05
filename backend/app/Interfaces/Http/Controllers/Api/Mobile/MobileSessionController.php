<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MobileSessionController extends Controller
{
    /**
     * Get sessions (appointments) with optional pagination.
     * Query params: date, serviceID, instructorID, per_page (default 15), page
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $serviceID = $request->query('serviceID');
        $instructorID = $request->query('instructorID');
        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));

        $query = AppointmentModel::query()
            ->with(['service', 'provider', 'bookings'])
            ->where('status', 'approved');

        if ($date) {
            $dateCarbon = Carbon::parse($date);
            $query->whereDate('booking_start', $dateCarbon->format('Y-m-d'));
        }
        if ($serviceID) {
            $query->where('service_id', $serviceID);
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

            return [
                'id' => (string) $appointment->id,
                'instructor' => $provider ? $provider->name : '',
                'service' => $service ? $service->name : '',
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
