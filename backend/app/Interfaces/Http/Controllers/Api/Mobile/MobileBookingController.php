<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileBookingController extends Controller
{
    /**
     * GET /api/v1/appointments/history
     * Returns the authenticated customer's appointment/booking history (paginated).
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 50);
        $page = max(1, (int) $request->get('page', 1));

        /** @var Customer $customer */
        $customer = $request->user();

        $bookings = BookingModel::query()
            ->where('customer_id', $customer->id)
            ->with(['appointment.service', 'appointment.provider', 'service'])
            ->orderByDesc('booked_at')
            ->paginate($perPage, ['*'], 'page', $page);

        $data = $bookings->getCollection()->map(function (BookingModel $booking) {
            $appointment = $booking->appointment;
            $service = $appointment?->service ?? $booking->service;
            $provider = $appointment?->provider;

            return [
                'id' => (string) $booking->id,
                'sessionID' => $booking->appointment_id ? (string) $booking->appointment_id : null,
                'serviceName' => $service?->name,
                'instructorName' => $provider?->name,
                'bookedAt' => $booking->booked_at?->toIso8601String(),
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
                'partySize' => $booking->party_size,
                'totalAmount' => (float) $booking->total_amount,
                'currency' => $booking->currency ?? 'USD',
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ], 200);
    }

    /**
     * Book a session (appointment). Uses authenticated customer.
     * Body: { sessionID, persons (optional, default 1) [, promoCode ] }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'persons' => 'nullable|integer|min:1|max:20',
            'promoCode' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $sessionID = (int) $data['sessionID'];
        $persons = (int) ($data['persons'] ?? 1);
        $promoCode = $data['promoCode'] ?? null;

        /** @var Customer $customer */
        $customer = $request->user();

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings'])
            ->find($sessionID);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        $service = $appointment->service;
        $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
        $currentBookings = $appointment->bookings->whereIn('status', ['confirmed', 'pending'])->sum('party_size');

        if (($currentBookings + $persons) > $maxCapacity) {
            return response()->json([
                'success' => false,
                'message' => 'Session is full',
            ], 400);
        }

        if (Carbon::parse($appointment->booking_start)->lte(Carbon::now())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book past sessions',
            ], 400);
        }

        $servicePrice = $service ? (float) ($service->price ?? 0) : 0;
        $totalPrice = $servicePrice * $persons;

        $promoRecord = null;
        if ($promoCode) {
            $promoRecord = \App\Infrastructure\Persistence\Eloquent\PromoCodeModel::query()
                ->where('code', $promoCode)
                ->first();
            if ($promoRecord && $promoRecord->isValid()) {
                $totalPrice = $totalPrice * (1 - (float) $promoRecord->percent_discount / 100);
            } else {
                $promoRecord = null;
            }
        }

        DB::beginTransaction();
        try {
            if ($promoRecord) {
                $promoRecord->increment('used_count');
            }

            $booking = BookingModel::query()->create([
                'customer_id' => $customer->id,
                'appointment_id' => $appointment->id,
                'event_id' => null,
                'event_instance_id' => null,
                'package_id' => null,
                'service_id' => $appointment->service_id,
                'provider_id' => $appointment->provider_id,
                'location_id' => $appointment->location_id,
                'status' => 'confirmed',
                'payment_status' => 'pending',
                'party_size' => $persons,
                'total_amount' => $totalPrice,
                'deposit_amount' => 0,
                'balance_amount' => $totalPrice,
                'currency' => 'USD',
                'channel' => 'mobile',
                'booked_at' => $appointment->booking_start,
            ]);

            \App\Infrastructure\Persistence\Eloquent\PaymentModel::query()->create([
                'booking_id' => $booking->id,
                'promo_code_id' => $promoRecord?->id,
                'provider' => 'on_site',
                'status' => 'paid',
                'amount' => $totalPrice,
                'currency' => 'USD',
                'paid_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => (string) $booking->id,
                    'sessionID' => (string) $appointment->id,
                    'customerId' => (string) $customer->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a booking. Only the authenticated customer's booking.
     * Body: { sessionID [, customerBookingId ] }
     */
    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'customerBookingId' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sessionID = (int) $validator->validated()['sessionID'];
        $bookingId = isset($validator->validated()['customerBookingId']) ? (int) $validator->validated()['customerBookingId'] : null;

        /** @var Customer $customer */
        $customer = $request->user();

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings'])
            ->find($sessionID);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        $service = $appointment->service;
        $minutesBeforeCancellation = (int) ($service->time_before ?? 0);
        $canCancelUntil = Carbon::parse($appointment->booking_start)->subMinutes($minutesBeforeCancellation);
        if (Carbon::now()->gt($canCancelUntil)) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation deadline has passed',
            ], 400);
        }

        $query = BookingModel::query()
            ->where('appointment_id', $sessionID)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['confirmed', 'pending']);

        if ($bookingId !== null) {
            $query->where('id', $bookingId);
        }

        $booking = $query->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Booking canceled successfully',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
