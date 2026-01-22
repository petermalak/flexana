<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MobileSessionController extends Controller
{
    /**
     * Get sessions based on filters
     * 
     * Query params: date, serviceID, instructorID
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $serviceID = $request->query('serviceID');
        $instructorID = $request->query('instructorID');

        $query = AmeliaAppointmentModel::on('wordpress')
            ->with(['service', 'provider', 'customerBookings'])
            ->where('status', 'approved');

        // Filter by date
        if ($date) {
            $dateCarbon = Carbon::parse($date);
            $query->whereDate('bookingStart', $dateCarbon->format('Y-m-d'));
        }

        // Filter by service
        if ($serviceID) {
            $query->where('serviceId', $serviceID);
        }

        // Filter by instructor/provider
        if ($instructorID) {
            $query->where('providerId', $instructorID);
        }

        $appointments = $query->get();

        $sessions = $appointments->map(function ($appointment) {
            $service = $appointment->service;
            $provider = $appointment->provider;
            $customerBookings = $appointment->customerBookings;
            
            // Count approved bookings
            $approvedBookings = $customerBookings->where('status', 'approved')->count();
            $totalPersons = $customerBookings->where('status', 'approved')->sum('persons');
            
            // Get service capacity
            $maxCapacity = $service ? ($service->maxCapacity ?? 1) : 1;
            $isFull = $totalPersons >= $maxCapacity;
            
            // Check if current user has a booking (you may need to pass user ID)
            $isBooked = false; // TODO: Check if current user has booking
            $canCancel = false; // TODO: Check cancellation rules
            $canBook = !$isFull && $appointment->bookingStart > Carbon::now();
            
            // Get cancellation minutes from service settings
            $settings = is_string($service->settings ?? null) 
                ? json_decode($service->settings, true) 
                : ($service->settings ?? []);
            $minutesBeforeCancellation = $settings['timeBefore'] ?? $service->timeBefore ?? 0;

            return [
                'id' => (string) $appointment->id,
                'instructor' => $provider 
                    ? trim(($provider->firstName ?? '') . ' ' . ($provider->lastName ?? ''))
                    : '',
                'service' => $service ? $service->name : '',
                'date' => $appointment->bookingStart->toIso8601String(),
                'isBooked' => $isBooked,
                'isFull' => $isFull,
                'canCancel' => $canCancel,
                'canBook' => $canBook,
                'minutesBeforeCancellation' => (int) $minutesBeforeCancellation,
            ];
        })
        ->values()
        ->toArray();

        return response()->json($sessions);
    }
}
