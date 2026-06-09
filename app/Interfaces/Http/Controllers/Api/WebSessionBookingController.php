<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Console\Commands\CategorizeServicesCommand;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Models\Category;
use App\Models\Customer;
use App\Support\ApiDateTime;
use App\Support\InternalNotificationMail;
use App\Support\PackagePurchaseExpiry;
use App\Support\PromoEmailText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Public website booking endpoint (no Sanctum).
 *
 * Keeps the mobile POST /api/v1/session-bookings unchanged (still requires auth:sanctum),
 * but allows the website (Amelia-like UX) to create bookings for guests.
 */
class WebSessionBookingController extends Controller
{
    /**
     * Book a session (appointment) as a guest.
     *
     * Body:
     *  - sessionID (required int)
     *  - spots (optional int, default 1)
     *  - promoCode (optional string)
     *  - isDropIn (optional bool, default true)
     *  - customer: { firstName, lastName, email, phone? }
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionID' => 'required|integer',
            'spots' => 'nullable|integer|min:1|max:20',
            'promoCode' => 'nullable|string|max:64',
            'isDropIn' => 'nullable|boolean',
            'customer.firstName' => 'required|string|max:80',
            'customer.lastName' => 'required|string|max:80',
            'customer.email' => 'required|email:rfc|max:190',
            'customer.phone' => 'nullable|string|max:40',
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
        $spots = (int) ($data['spots'] ?? 1);
        $promoCode = $data['promoCode'] ?? null;
        $isDropIn = array_key_exists('isDropIn', $data) ? (bool) $data['isDropIn'] : true;

        if ($isDropIn) {
            return response()->json([
                'success' => false,
                'message' => 'Drop-in bookings are only created after successful payment.',
            ], 400);
        }   

        $customerPayload = $data['customer'] ?? [];
        $email = strtolower(trim((string) ($customerPayload['email'] ?? '')));

        /** @var Customer $customer */
        $customer = Customer::query()->where('email', $email)->first();
        if (! $customer) {
            $customer = Customer::query()->create([
                'first_name' => (string) ($customerPayload['firstName'] ?? ''),
                'last_name' => (string) ($customerPayload['lastName'] ?? ''),
                'email' => $email,
                'phone' => (string) ($customerPayload['phone'] ?? ''),
                'source' => 'web',
                'timezone' => (string) config('app.timezone'),
            ]);
        } else {
            $customer->fill([
                'first_name' => (string) ($customerPayload['firstName'] ?? $customer->first_name),
                'last_name' => (string) ($customerPayload['lastName'] ?? $customer->last_name),
                'phone' => (string) ($customerPayload['phone'] ?? $customer->phone),
                'source' => $customer->source ?: 'web',
            ])->save();
        }

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

        if (($currentBookings + $spots) > $maxCapacity) {
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
            $purchaseToUse = $this->findValidPurchaseForCategory(
                (int) $customer->id,
                $sessionCategory,
                $spots,
                Carbon::parse($appointment->booking_start),
            );
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
            $subtotalBeforePromo = $servicePrice * $spots;
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
                'party_size' => $spots,
                'total_amount' => $totalPrice,
                'deposit_amount' => 0,
                'balance_amount' => $totalPrice,
                'currency' => 'USD',
                'channel' => 'web',
                'is_drop_in' => $isDropIn,
                'answers' => [
                    'isDropIn' => $isDropIn,
                    'spots' => $spots,
                    'guest' => true,
                ],
                'booked_at' => $appointment->booking_start,
            ]);

            if ($purchaseToUse) {
                $purchaseToUse->decrement('remaining_sessions', $spots);
            }

            \App\Infrastructure\Persistence\Eloquent\PaymentModel::query()->create([
                'booking_id' => $booking->id,
                'promo_code_id' => $promoRecord?->id,
                'provider' => 'on_site',
                'status' => $isDropIn ? 'pending' : 'paid',
                'amount' => $totalPrice,
                'currency' => 'USD',
                'paid_at' => $isDropIn ? null : now(),
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
                    'isDropIn' => (bool) $booking->is_drop_in,
                    'spots' => (int) $booking->party_size,
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
            . "* Spots: " . ((int) ($booking->party_size ?? 1)) . "\n\n"
            . "* Class: " . ($service?->name ?? 'Unknown') . "\n\n"
            . "* Day: {$appointmentDate}\n\n"
            . "* Time: {$appointmentTime}\n\n"
            . "* Instructor: " . ($provider?->name ?? 'Unknown') . "\n\n"
            . "* Type: " . ($service?->description ?? '') . "\n\n"
            . "* Channel: website\n\n"
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

    private function sessionCategoryFromService(?ServiceModel $service): ?string
    {
        if (! $service) {
            return null;
        }
        $category = $service->relationLoaded('category') ? $service->category : null;
        if ($category) {
            $name = strtolower((string) ($category->name ?? ''));
            $slug = strtolower((string) ($category->slug ?? ''));
            if (str_contains($name, 'yoga') && str_contains($name, 'pilates')) {
                return 'Yoga';
            }
            if ($slug === 'yoga' || (str_contains($name, 'yoga') && ! str_contains($name, 'pilates'))) {
                return 'Yoga';
            }
            if (in_array($slug, ['reformer-pilates', 'pilates', 'reformer'], true)
                || str_contains($name, 'reformer')
                || (str_contains($name, 'pilates') && ! str_contains($name, 'yoga'))) {
                return 'Reformer Pilates';
            }
        }
        if (! $service->name) {
            return null;
        }
        $serviceText = trim(($service->name ?? '') . ' ' . ($service->description ?? ''));

        return CategorizeServicesCommand::inferCategoryNameFromText($serviceText) ?? 'Yoga';
    }

    private function findValidPurchaseForCategory(
        int $customerId,
        ?string $sessionCategory,
        int $persons,
        Carbon $sessionDate,
    ): ?CustomerPackagePurchaseModel {
        if ($sessionCategory === null) {
            return null;
        }
        $bizTz = (string) config('app.business_timezone');
        /** @var \Illuminate\Database\Eloquent\Collection<int, CustomerPackagePurchaseModel> $purchases */
        $purchases = CustomerPackagePurchaseModel::query()
            ->with(['package.services'])
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>=', $persons)
            ->whereNotNull('package_id')
            ->orderByDesc('purchase_date')
            ->get();

        foreach ($purchases as $purchase) {
            /** @var CustomerPackagePurchaseModel $purchase */
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
            if (! PackagePurchaseExpiry::coversSessionDate($expiresAt, $sessionDate, $bizTz)) {
                continue;
            }

            return $purchase;
        }

        return null;
    }

    private function packageCategory($package): ?string
    {
        if (! $package) {
            return null;
        }
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
}

