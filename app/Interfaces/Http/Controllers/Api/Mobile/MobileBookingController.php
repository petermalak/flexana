<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Models\Customer;
use App\Support\ApiDateTime;
use App\Support\PackagePurchaseExpiry;
use App\Support\InternalNotificationMail;
use App\Support\PromoEmailText;
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

            $sessionDate = null;
            $sessionTime = null;
            $sessionStart = null;
            $sessionStartUtc = null;
            $sessionEnd = null;
            $sessionEndUtc = null;

            if ($appointment && $appointment->booking_start) {
                $sessionStart = ApiDateTime::toBusinessIso8601($appointment->booking_start);
                $sessionStartUtc = ApiDateTime::toUtcIso8601($appointment->booking_start);
                $sessionDate = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d');
                $sessionTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i:s');
            }

            if ($appointment && $appointment->booking_end) {
                $sessionEnd = ApiDateTime::toBusinessIso8601($appointment->booking_end);
                $sessionEndUtc = ApiDateTime::toUtcIso8601($appointment->booking_end);
            }

            $isDropIn = $booking->is_drop_in ?? ($booking->answers['isDropIn'] ?? false);

            return [
                'id' => (string) $booking->id,
                'sessionID' => $booking->appointment_id ? (string) $booking->appointment_id : null,
                'serviceName' => $service?->name,
                'instructorName' => $provider?->name,
                'bookedAt' => ApiDateTime::toBusinessIso8601($booking->booked_at),
                'bookedAtUtc' => ApiDateTime::toUtcIso8601($booking->booked_at),
                'sessionDate' => $sessionDate,
                'sessionTime' => $sessionTime,
                'sessionStart' => $sessionStart,
                'sessionStartUtc' => $sessionStartUtc,
                'sessionEnd' => $sessionEnd,
                'sessionEndUtc' => $sessionEndUtc,
                'status' => $booking->status,
                'paymentStatus' => $booking->payment_status,
                'partySize' => $booking->party_size,
                'totalAmount' => (float) $booking->total_amount,
                'currency' => $booking->currency ?? 'USD',
                'isDropIn' => (bool) $isDropIn,
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
     * Body: { sessionID, persons (optional, default 1) [, promoCode, isDropIn ] }
     * isDropIn: boolean flag to clarify whether this booking is a drop-in
     *           (true) or taken from the customer's package sessions (false).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'persons' => 'nullable|integer|min:1|max:20',
            'promoCode' => 'nullable|string|max:64',
            'isDropIn' => 'nullable|boolean',
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
        $isDropIn = array_key_exists('isDropIn', $data) ? (bool) $data['isDropIn'] : true;

        /** @var Customer $customer */
        $customer = $request->user();

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings', 'provider'])
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

        $sessionCategory = $this->sessionCategoryFromService($service);
        $purchaseToUse = null;
        $packageId = null;
        $customerPackagePurchaseId = null;
        $totalPrice = 0;
        $promoRecord = null;
        $subtotalBeforePromo = null;

        if (! $isDropIn) {
            $purchaseToUse = $this->findValidPurchaseForCategory($customer->id, $sessionCategory, $persons);
            if (! $purchaseToUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'No package with remaining sessions for this category (Yoga/Reformer Pilates). Book as drop-in or purchase a package.',
                ], 400);
            }
            $packageId = $purchaseToUse->package_id;
            $customerPackagePurchaseId = $purchaseToUse->id;
        } else {
            $servicePrice = $service ? (float) ($service->price ?? 0) : 0;
            $subtotalBeforePromo = $servicePrice * $persons;
            $totalPrice = $subtotalBeforePromo;
            $promoRecord = null;
            if ($promoCode) {
                $promoRecord = PromoCodeModel::findByCode($promoCode);
                if ($promoRecord && $promoRecord->isValidForCustomer((int) $customer->id)) {
                    $totalPrice = $totalPrice * (1 - (float) $promoRecord->percent_discount / 100);
                } else {
                    $promoRecord = null;
                }
            }
        }

        DB::beginTransaction();
        try {
            // Count promo use for drop-ins whenever a valid promo was applied (including 100% off → totalPrice 0).
            if ($isDropIn && $promoRecord) {
                if (! $promoRecord->incrementUsageIfAllowed((int) $customer->id)) {
                    throw new \RuntimeException('Promo code usage limit was reached.');
                }
            }

            $booking = BookingModel::query()->create([
                'customer_id' => $customer->id,
                'appointment_id' => $appointment->id,
                'event_id' => null,
                'event_instance_id' => null,
                'package_id' => $packageId,
                'customer_package_purchase_id' => $customerPackagePurchaseId,
                'service_id' => $appointment->service_id,
                'provider_id' => $appointment->provider_id,
                'location_id' => $appointment->location_id,
                'status' => 'confirmed',
                'payment_status' => $isDropIn ? 'pending' : 'paid',
                'party_size' => $persons,
                'total_amount' => $totalPrice,
                'deposit_amount' => 0,
                'balance_amount' => $totalPrice,
                'currency' => 'USD',
                'channel' => 'mobile',
                'is_drop_in' => $isDropIn,
                'answers' => [
                    'isDropIn' => $isDropIn,
                ],
                'booked_at' => $appointment->booking_start,
            ]);

            if ($purchaseToUse) {
                $purchaseToUse->decrement('remaining_sessions', $persons);
            }

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

            try {
                $this->sendBookingCreatedEmail(
                    $booking,
                    $appointment,
                    $customer,
                    $promoRecord,
                    $isDropIn ? $subtotalBeforePromo : null,
                );
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => [
                    'id' => (string) $booking->id,
                    'sessionID' => (string) $appointment->id,
                    'customerId' => (string) $customer->id,
                    'isDropIn' => $booking->is_drop_in,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            $message = $e->getMessage();
            if (str_contains($message, 'Promo code usage limit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already used this promo code the maximum number of times.',
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking',
                'error' => $message,
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
            ->with(['service', 'bookings', 'provider'])
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
            if ($booking->customer_package_purchase_id) {
                $purchase = CustomerPackagePurchaseModel::query()->find($booking->customer_package_purchase_id);
                if ($purchase) {
                    $purchase->increment('remaining_sessions', (int) $booking->party_size);
                }
            }
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            DB::commit();
                try {
                    $this->sendBookingCancelledEmail($booking, $appointment, $customer);
                } catch (\Throwable $e) {
                    report($e);
                }
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

    /**
     * Session category (Yoga / Reformer Pilates) from service name. Used to match package category.
     */
    private function sessionCategoryFromService(?ServiceModel $service): ?string
    {
        if (! $service || ! $service->name) {
            return null;
        }
        $name = strtolower($service->name);
        if (str_contains($name, 'reformer') || str_contains($name, 'reform pilates')) {
            return 'Reformer Pilates';
        }
        if (str_contains($name, 'yoga')) {
            return 'Yoga';
        }
        return 'Yoga';
    }

    /**
     * Find an active customer package purchase with matching category and enough remaining sessions.
     * Prefers most recently purchased. Excludes expired (by package_duration + purchase_date).
     */
    private function findValidPurchaseForCategory(int $customerId, ?string $sessionCategory, int $persons): ?CustomerPackagePurchaseModel
    {
        if ($sessionCategory === null) {
            return null;
        }
        $bizTz = (string) config('app.business_timezone');
        $today = Carbon::now($bizTz)->startOfDay();
        $purchases = CustomerPackagePurchaseModel::query()
            ->with(['package.services'])
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>=', $persons)
            ->whereNotNull('package_id')
            ->orderByDesc('purchase_date')
            ->get();

        foreach ($purchases as $purchase) {
            $package = $purchase->package;
            if (! $package) {
                continue;
            }
            $packageCategory = $this->packageCategory($package);
            if ($packageCategory !== $sessionCategory) {
                continue;
            }
            $expiresAt = PackagePurchaseExpiry::expiresAt(
                $package,
                $purchase->purchase_date,
                $purchase->amelia_package_id,
                (bool) $purchase->expires_by_months_only,
            );
            if ($expiresAt !== null && $expiresAt->copy()->timezone($bizTz)->startOfDay()->lt($today)) {
                continue;
            }
            return $purchase;
        }

        return null;
    }

    /**
     * Determine package category (Yoga / Reformer Pilates) from package.service_type (admin-set) first,
     * then fallback to service names if service_type is not set.
     */
    private function packageCategory($package): ?string
    {
        if (! $package) {
            return null;
        }
        // Prioritize admin-set service_type field (authoritative source)
        $serviceType = $package->service_type ?? null;
        if ($serviceType !== null) {
            $lower = strtolower((string) $serviceType);
            if (str_contains($lower, 'reformer') || str_contains($lower, 'reform')) {
                return 'Reformer Pilates';
            }
            if (str_contains($lower, 'yoga')) {
                return 'Yoga';
            }
        }
        // Fallback: determine from service names if service_type is not set
        if ($package->relationLoaded('services') && $package->services->isNotEmpty()) {
            $yogaCount = 0;
            $reformerCount = 0;
            foreach ($package->services as $service) {
                $name = strtolower($service->name ?? '');
                if (str_contains($name, 'reformer') || str_contains($name, 'reform pilates')) {
                    $reformerCount++;
                } elseif (str_contains($name, 'yoga')) {
                    $yogaCount++;
                }
            }
            if ($reformerCount > 0 && $reformerCount >= $yogaCount) {
                return 'Reformer Pilates';
            }
            if ($yogaCount > 0) {
                return 'Yoga';
            }
        }
        return null;
    }

    /**
     * Send confirmation email after a booking is created.
     */
    private function sendBookingCreatedEmail(
        BookingModel $booking,
        AppointmentModel $appointment,
        Customer $customer,
        ?PromoCodeModel $promo = null,
        ?float $subtotalBeforePromo = null,
    ): void {
        if (! $customer->email) {
            return;
        }

        $service = $appointment->service;
        $provider = $appointment->provider;

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDate = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d');
        $appointmentTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i');

        $promoLines = $promo !== null
            ? PromoEmailText::appliedSection(
                $promo,
                $subtotalBeforePromo ?? (float) $booking->total_amount,
                (float) $booking->total_amount,
            )
            : '';

        $body = "Thank you for booking with Flexana!\n\n"
            . "Booking Details\n\n"
            . "* Name: {$customerName},\n\n"
            . "* Email: {$customer->email}\n\n"
            . "* Phone: {$customer->phone}\n\n"
            . "* Class: " . ($service?->name ?? 'Unknown') . "\n\n"
            . "* Day: {$appointmentDate}\n\n"
            . "* Time: {$appointmentTime}\n\n"
            . "* Instructor: " . ($provider?->name ?? 'Unknown') . "\n\n"
            . "* Type: " . ($service?->description ?? '') . "\n\n"
            . $promoLines
            . "If you need to cancel, please do so at least 24 hours in advance via your Flexana account or by contacting us directly.\n\n"
            . "You can contact us at +20 122 0221100 to reschedule your session or request a refund.\n\n"
            . "We look forward to seeing you on the mat!\n\n"
            . "Flexana Team";

        $subject = 'Your Flexana booking confirmation';

        InternalNotificationMail::sendCustomerAndInternalCopy(
            $body,
            $subject,
            $customer->email,
            $customerName,
        );
    }

    /**
     * Send cancellation email after a booking is cancelled.
     */
    private function sendBookingCancelledEmail(BookingModel $booking, AppointmentModel $appointment, Customer $customer): void
    {
        if (! $customer->email) {
            return;
        }

        $service = $appointment->service;

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDateTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d H:i');

        $body = "Dear {$customerName},\n"
            . "Phone {$customer->phone}\n"
            . "Your " . ($service?->name ?? 'session') . " appointment, scheduled on {$appointmentDateTime} has been canceled.\n"
            . "Thank you for choosing our company,\n"
            . "Flexana Team";

        $subject = 'Your Flexana booking has been cancelled';

        InternalNotificationMail::sendCustomerAndInternalCopy(
            $body,
            $subject,
            $customer->email,
            $customerName,
        );
    }
}
