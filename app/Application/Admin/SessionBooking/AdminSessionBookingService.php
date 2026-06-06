<?php

namespace App\Application\Admin\SessionBooking;

use App\Application\Bookings\CancelSessionBookingService;
use App\Domain\Promo\Enums\PromoApplicableType;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Support\PackagePurchaseExpiry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AdminSessionBookingService
{
    /**
     * Create an appointment booking from the admin panel.
     * Mirrors the mobile session-booking rules (without touching mobile code).
     *
     * @param  array{
     *   appointment_id:int,
     *   customer_id:int,
     *   spots:int,
     *   isDropIn:bool,
     *   promoCode?:string|null,
     *   payment_status?:string|null,
     *   status?:string|null
     * }  $data
     */
    public function create(array $data): BookingModel
    {
        $appointmentId = (int) $data['appointment_id'];
        $customerId = (int) $data['customer_id'];
        $spots = (int) $data['spots'];
        $isDropIn = (bool) $data['isDropIn'];
        $promoCode = $data['promoCode'] ?? null;
        // Mobile parity: booking status is always confirmed.
        $status = 'confirmed';
        // Mobile parity: drop-in => pending, package => paid.
        $paymentStatus = $isDropIn ? 'pending' : 'paid';

        $appointment = AppointmentModel::query()
            ->with(['service', 'bookings', 'provider'])
            ->find($appointmentId);

        if (! $appointment) {
            throw new \RuntimeException('Session not found');
        }

        $service = $appointment->service;
        $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
        $currentBookings = $appointment->bookings->whereIn('status', ['confirmed', 'pending'])->sum('party_size');

        if (($currentBookings + $spots) > $maxCapacity) {
            throw new \RuntimeException('Session is full');
        }

        if (Carbon::parse($appointment->booking_start)->lte(Carbon::now())) {
            throw new \RuntimeException('Cannot book past sessions');
        }

        $sessionCategory = $this->sessionCategoryFromService($service);
        $purchaseToUse = null;
        $packageId = null;
        $customerPackagePurchaseId = null;
        $promoRecord = null;

        $totalPrice = 0.0;
        $subtotalBeforePromo = null;

        if (! $isDropIn) {
            $purchaseToUse = $this->findValidPurchaseForCategory($customerId, $sessionCategory, $spots);
            if (! $purchaseToUse) {
                throw new \RuntimeException('No package with remaining sessions for this category (Yoga/Reformer Pilates). Book as drop-in or purchase a package.');
            }
            $packageId = $purchaseToUse->package_id;
            $customerPackagePurchaseId = $purchaseToUse->id;
        } else {
            $servicePrice = $service ? (float) ($service->price ?? 0) : 0.0;
            $subtotalBeforePromo = $servicePrice * $spots;
            $totalPrice = $subtotalBeforePromo;

            if ($promoCode) {
                $promoRecord = PromoCodeModel::resolveForCustomer(
                    $promoCode,
                    $customerId,
                    PromoApplicableType::DropIns,
                );
                if ($promoRecord) {
                    $totalPrice = $totalPrice * (1 - (float) $promoRecord->percent_discount / 100);
                }
            }
        }

        return DB::transaction(function () use (
            $appointment,
            $customerId,
            $spots,
            $isDropIn,
            $packageId,
            $customerPackagePurchaseId,
            $purchaseToUse,
            $promoRecord,
            $totalPrice,
            $paymentStatus,
            $status,
            $subtotalBeforePromo,
        ): BookingModel {
            if ($isDropIn && $promoRecord) {
                if (! $promoRecord->incrementUsageIfAllowed($customerId)) {
                    throw new \RuntimeException('Promo code usage limit was reached.');
                }
            }

            $booking = BookingModel::query()->create([
                'customer_id' => $customerId,
                'appointment_id' => $appointment->id,
                'event_id' => null,
                'event_instance_id' => null,
                'package_id' => $packageId,
                'customer_package_purchase_id' => $customerPackagePurchaseId,
                'service_id' => $appointment->service_id,
                'provider_id' => $appointment->provider_id,
                'location_id' => $appointment->location_id,
                'status' => $status,
                'payment_status' => $paymentStatus,
                'party_size' => $spots,
                'total_amount' => $totalPrice,
                'deposit_amount' => 0,
                'balance_amount' => $totalPrice,
                'currency' => 'USD',
                'channel' => 'mobile',
                'is_drop_in' => $isDropIn,
                'answers' => [
                    'isDropIn' => $isDropIn,
                    'spots' => $spots,
                ],
                'booked_at' => $appointment->booking_start,
            ]);

            if ($purchaseToUse) {
                $purchaseToUse->decrement('remaining_sessions', $spots);
            }

            // Mobile parity: payment row is created with status=paid/provider=on_site.
            $paymentRowStatus = 'paid';
            PaymentModel::query()->create([
                'booking_id' => $booking->id,
                'promo_code_id' => $promoRecord?->id,
                'provider' => 'on_site',
                'status' => $paymentRowStatus,
                'amount' => $totalPrice,
                'currency' => 'USD',
                'paid_at' => now(),
            ]);

            return $booking;
        });
    }

    /**
     * Cancel a session booking from the admin panel.
     * Frees capacity on the session and restores package sessions when applicable.
     */
    public function cancel(BookingModel $booking, bool $sendEmail = true): BookingModel
    {
        return app(CancelSessionBookingService::class)->cancel(
            $booking,
            enforceCancellationDeadline: false,
            sendEmail: $sendEmail,
        );
    }

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

    private function findValidPurchaseForCategory(int $customerId, ?string $sessionCategory, int $spots): ?CustomerPackagePurchaseModel
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
            ->where('remaining_sessions', '>=', $spots)
            ->whereNotNull('package_id')
            ->orderBy('purchase_date')
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

