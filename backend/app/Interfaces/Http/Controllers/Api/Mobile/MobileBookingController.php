<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobileBookingController extends Controller
{
    /**
     * Book a session
     * 
     * Body: { sessionID, customerId (optional), customer (optional) }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'customerId' => 'nullable|integer',
            'customer' => 'nullable|array',
            'customer.firstName' => 'required_with:customer|string',
            'customer.lastName' => 'nullable|string',
            'customer.email' => 'nullable|email',
            'customer.phone' => 'nullable|string',
            'persons' => 'nullable|integer|min:1|default:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $sessionID = $data['sessionID'];
        $persons = $data['persons'] ?? 1;

        // Get appointment
        $appointment = AmeliaAppointmentModel::on('wordpress')
            ->with(['service', 'customerBookings'])
            ->find($sessionID);

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        // Validate customerId if provided
        if (!empty($data['customerId'])) {
            $customerExists = AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->where('id', $data['customerId'])
                ->exists();
            
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }
        }

        // Check if session is full
        $service = $appointment->service;
        $maxCapacity = $service ? ($service->maxCapacity ?? 1) : 1;
        $currentBookings = $appointment->customerBookings->where('status', 'approved')->sum('persons');
        
        if (($currentBookings + $persons) > $maxCapacity) {
            return response()->json([
                'success' => false,
                'message' => 'Session is full',
            ], 400);
        }

        // Check if session is in the past
        if ($appointment->bookingStart <= Carbon::now()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot book past sessions',
            ], 400);
        }

        // Get or create customer
        $customer = null;
        if (!empty($data['customerId'])) {
            $customer = AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->find($data['customerId']);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }
        } elseif (!empty($data['customer'])) {
            // Create new customer
            $customerData = $data['customer'];
            $customer = AmeliaUserModel::on('wordpress')->create([
                'firstName' => $customerData['firstName'],
                'lastName' => $customerData['lastName'] ?? null,
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'type' => 'customer',
                'status' => 'visible',
                'created' => Carbon::now(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Customer ID or customer data is required',
            ], 422);
        }

        // Calculate price
        $servicePrice = $service ? ($service->price ?? 0) : 0;
        $totalPrice = $servicePrice * $persons;

        // Create customer booking
        DB::connection('wordpress')->beginTransaction();
        try {
            $customerBooking = AmeliaCustomerBookingModel::on('wordpress')->create([
                'appointmentId' => $appointment->id,
                'customerId' => $customer->id,
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
                    'customerId' => (string) $customer->id,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::connection('wordpress')->rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a booking
     * 
     * Body: { sessionID, customerBookingId (optional) }
     * 
     * @param Request $request
     * @return JsonResponse
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
        $sessionID = $data['sessionID'];
        $customerBookingId = $data['customerBookingId'] ?? null;

        // Get appointment
        $appointment = AmeliaAppointmentModel::on('wordpress')
            ->with(['service', 'customerBookings'])
            ->find($sessionID);

        if (!$appointment) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found',
            ], 404);
        }

        // Check cancellation time
        $service = $appointment->service;
        $settings = is_string($service->settings ?? null) 
            ? json_decode($service->settings, true) 
            : ($service->settings ?? []);
        $minutesBeforeCancellation = $settings['timeBefore'] ?? $service->timeBefore ?? 0;
        
        // Calculate the deadline (booking start time minus cancellation minutes)
        $canCancelUntil = Carbon::parse($appointment->bookingStart)->subMinutes($minutesBeforeCancellation);
        if (Carbon::now() > $canCancelUntil) {
            return response()->json([
                'success' => false,
                'message' => 'Cancellation deadline has passed',
            ], 400);
        }

        // Find customer booking
        $query = AmeliaCustomerBookingModel::on('wordpress')
            ->where('appointmentId', $sessionID)
            ->where('status', 'approved');

        if ($customerBookingId) {
            $query->where('id', $customerBookingId);
        }

        $customerBooking = $query->first();

        if (!$customerBooking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found',
            ], 404);
        }

        // Cancel booking
        DB::connection('wordpress')->beginTransaction();
        try {
            $customerBooking->update([
                'status' => 'canceled',
            ]);

            DB::connection('wordpress')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking canceled successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::connection('wordpress')->rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
