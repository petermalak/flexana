<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Application\Auth\AmeliaCustomerResolver;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileBookingController extends Controller
{
    public function __construct(
        private readonly AmeliaCustomerResolver $ameliaResolver,
    ) {
    }

    /**
     * Book a session. Uses authenticated customer (Bearer token).
     * Body: { sessionID, persons (optional, default 1) }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'persons' => 'nullable|integer|min:1|max:20',
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

        /** @var Customer $laravelCustomer */
        $laravelCustomer = $request->user();

        $appointment = AmeliaAppointmentModel::on('wordpress')
            ->with(['service', 'customerBookings'])
            ->find($sessionID);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        $service = $appointment->service;
        $maxCapacity = $service ? ($service->maxCapacity ?? 1) : 1;
        $currentBookings = $appointment->customerBookings->where('status', 'approved')->sum('persons');

        if (($currentBookings + $persons) > $maxCapacity) {
            return response()->json([
                'success' => false,
                'message' => 'Session is full',
            ], 400);
        }

        if ($appointment->bookingStart <= Carbon::now()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book past sessions',
            ], 400);
        }

        $ameliaUser = $this->ameliaResolver->resolveAmeliaUser($laravelCustomer);
        if (! $ameliaUser) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve or create Amelia customer.',
            ], 500);
        }

        $servicePrice = $service ? ($service->price ?? 0) : 0;
        $totalPrice = $servicePrice * $persons;

        DB::connection('wordpress')->beginTransaction();
        try {
            $customerBooking = AmeliaCustomerBookingModel::on('wordpress')->create([
                'appointmentId' => $appointment->id,
                'customerId' => $ameliaUser->id,
                'status' => 'approved',
                'price' => $totalPrice,
                'persons' => $persons,
                'created' => Carbon::now(),
            ]);

            DB::connection('wordpress')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => (string) $customerBooking->id,
                    'sessionID' => (string) $appointment->id,
                    'customerId' => (string) $laravelCustomer->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::connection('wordpress')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a booking. Only the authenticated customer's booking can be canceled.
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

        $data = $validator->validated();
        $sessionID = (int) $data['sessionID'];
        $customerBookingId = isset($data['customerBookingId']) ? (int) $data['customerBookingId'] : null;

        /** @var Customer $laravelCustomer */
        $laravelCustomer = $request->user();
        $ameliaUserId = $laravelCustomer->amelia_user_id;
        if (! $ameliaUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        $appointment = AmeliaAppointmentModel::on('wordpress')
            ->with(['service', 'customerBookings'])
            ->find($sessionID);

        if (! $appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        $service = $appointment->service;
        $settings = is_string($service->settings ?? null)
            ? json_decode($service->settings, true)
            : ($service->settings ?? []);
        $minutesBeforeCancellation = $settings['timeBefore'] ?? $service->timeBefore ?? 0;
        $canCancelUntil = Carbon::parse($appointment->bookingStart)->subMinutes($minutesBeforeCancellation);
        if (Carbon::now() > $canCancelUntil) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation deadline has passed',
            ], 400);
        }

        $query = AmeliaCustomerBookingModel::on('wordpress')
            ->where('appointmentId', $sessionID)
            ->where('customerId', $ameliaUserId)
            ->where('status', 'approved');

        if ($customerBookingId !== null) {
            $query->where('id', $customerBookingId);
        }

        $customerBooking = $query->first();

        if (! $customerBooking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        DB::connection('wordpress')->beginTransaction();
        try {
            $customerBooking->update(['status' => 'canceled']);
            DB::connection('wordpress')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking canceled successfully',
            ], 200);
        } catch (\Throwable $e) {
            DB::connection('wordpress')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
