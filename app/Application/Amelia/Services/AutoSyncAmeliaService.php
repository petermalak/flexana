<?php

namespace App\Application\Amelia\Services;

use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPaymentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaProviderServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service to automatically sync/clone Amelia (WordPress) data to Laravel MySQL database
 * whenever data is read from WordPress.
 */
final class AutoSyncAmeliaService
{
    /**
     * Sync an Amelia user (customer/staff) to Laravel database
     */
    public function syncUser(AmeliaUserModel $ameliaUser): Customer|StaffModel|null
    {
        try {
            if ($ameliaUser->type === 'customer') {
                return $this->syncCustomer($ameliaUser);
            } elseif (in_array($ameliaUser->type, ['provider', 'manager', 'admin'])) {
                return $this->syncStaff($ameliaUser);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to auto-sync Amelia user', [
                'amelia_user_id' => $ameliaUser->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Sync an Amelia customer to Laravel Customer model
     */
    public function syncCustomer(AmeliaUserModel $ameliaUser): ?Customer
    {
        $existing = Customer::query()
            ->where('amelia_user_id', $ameliaUser->id)
            ->first();

        if ($existing) {
            // Update existing customer
            $existing->update([
                'first_name' => $ameliaUser->firstName ?? $existing->first_name,
                'last_name' => $ameliaUser->lastName ?? $existing->last_name,
                'email' => $ameliaUser->email ?? $existing->email,
                'phone' => $ameliaUser->phone ?? $existing->phone,
            ]);

            return $existing;
        }

        // Create new customer
        return Customer::query()->create([
            'amelia_user_id' => $ameliaUser->id,
            'first_name' => $ameliaUser->firstName ?? 'Customer',
            'last_name' => $ameliaUser->lastName,
            'email' => $ameliaUser->email,
            'phone' => $ameliaUser->phone,
            'source' => 'amelia_sync',
        ]);
    }

    /**
     * Sync an Amelia staff/provider to Laravel Staff model
     */
    public function syncStaff(AmeliaUserModel $ameliaUser): ?StaffModel
    {
        $existing = StaffModel::query()
            ->where('amelia_user_id', $ameliaUser->id)
            ->first();

        if ($existing) {
            $existing->update([
                'name' => trim(($ameliaUser->firstName ?? '') . ' ' . ($ameliaUser->lastName ?? '')),
                'email' => $ameliaUser->email ?? $existing->email,
                'phone' => $ameliaUser->phone ?? $existing->phone,
                'is_active' => ($ameliaUser->status ?? 'visible') === 'visible',
            ]);

            return $existing;
        }

        return StaffModel::query()->create([
            'amelia_user_id' => $ameliaUser->id,
            'name' => trim(($ameliaUser->firstName ?? '') . ' ' . ($ameliaUser->lastName ?? '')) ?: 'Staff ' . $ameliaUser->id,
            'email' => $ameliaUser->email,
            'phone' => $ameliaUser->phone,
            'is_active' => ($ameliaUser->status ?? 'visible') === 'visible',
        ]);
    }

    /**
     * Sync an Amelia service to Laravel Service model
     */
    public function syncService(AmeliaServiceModel $ameliaService): ?ServiceModel
    {
        $existing = ServiceModel::query()
            ->where('amelia_service_id', $ameliaService->id)
            ->first();

        if ($existing) {
            $existing->update([
                'name' => $ameliaService->name ?? $existing->name,
                'description' => $ameliaService->description ?? $existing->description,
                'duration' => (int) ($ameliaService->duration ?? $existing->duration ?? 0),
                'price' => (float) ($ameliaService->price ?? $existing->price ?? 0),
                'min_capacity' => (int) ($ameliaService->minCapacity ?? $existing->min_capacity ?? 1),
                'max_capacity' => (int) ($ameliaService->maxCapacity ?? $existing->max_capacity ?? 1),
                'status' => ($ameliaService->status ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
            ]);

            return $existing;
        }

        return ServiceModel::query()->create([
            'amelia_service_id' => $ameliaService->id,
            'name' => $ameliaService->name ?? 'Service ' . $ameliaService->id,
            'description' => $ameliaService->description,
            'duration' => (int) ($ameliaService->duration ?? 0),
            'price' => (float) ($ameliaService->price ?? 0),
            'min_capacity' => (int) ($ameliaService->minCapacity ?? 1),
            'max_capacity' => (int) ($ameliaService->maxCapacity ?? 1),
            'status' => ($ameliaService->status ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
        ]);
    }

    /**
     * Sync an Amelia package to Laravel Package model
     */
    public function syncPackage(AmeliaPackageModel $ameliaPackage): ?PackageModel
    {
        $existing = PackageModel::query()
            ->where('amelia_package_id', $ameliaPackage->id)
            ->first();

        $totalSessions = (int) ($ameliaPackage->quantity ?? $ameliaPackage->durationCount ?? 1);
        if ($totalSessions < 1) {
            $totalSessions = 1;
        }

        if ($existing) {
            $existing->update([
                'title' => $ameliaPackage->name ?? $existing->title,
                'description' => $ameliaPackage->description ?? $existing->description,
                'total_sessions' => $totalSessions,
                'price' => (float) ($ameliaPackage->price ?? $existing->price ?? 0),
                'discount' => (float) ($ameliaPackage->discount ?? $existing->discount ?? 0),
                'expiry' => $ameliaPackage->endDate ?? $existing->expiry,
                'status' => ($ameliaPackage->status ?? 'visible') === 'hidden' ? 'hidden' : 'active',
            ]);

            return $existing;
        }

        return PackageModel::query()->create([
            'amelia_package_id' => $ameliaPackage->id,
            'title' => $ameliaPackage->name ?? 'Package ' . $ameliaPackage->id,
            'description' => $ameliaPackage->description,
            'total_sessions' => $totalSessions,
            'price' => (float) ($ameliaPackage->price ?? 0),
            'discount' => (float) ($ameliaPackage->discount ?? 0),
            'expiry' => $ameliaPackage->endDate,
            'status' => ($ameliaPackage->status ?? 'visible') === 'hidden' ? 'hidden' : 'active',
        ]);
    }

    /**
     * Sync an Amelia appointment to Laravel Appointment model
     */
    public function syncAppointment(AmeliaAppointmentModel $ameliaAppointment): ?AppointmentModel
    {
        // First sync dependencies
        $service = ServiceModel::query()->where('amelia_service_id', $ameliaAppointment->serviceId)->first();
        if (! $service) {
            $ameliaService = AmeliaServiceModel::on('wordpress')->find($ameliaAppointment->serviceId);
            if ($ameliaService) {
                $service = $this->syncService($ameliaService);
            }
        }

        $provider = StaffModel::query()->where('amelia_user_id', $ameliaAppointment->providerId)->first();
        if (! $provider) {
            $ameliaProvider = AmeliaUserModel::on('wordpress')->find($ameliaAppointment->providerId);
            if ($ameliaProvider) {
                $provider = $this->syncStaff($ameliaProvider);
            }
        }

        if (! $service || ! $provider) {
            return null;
        }

        $package = null;
        if ($ameliaAppointment->packageId) {
            $package = PackageModel::query()->where('amelia_package_id', $ameliaAppointment->packageId)->first();
            if (! $package) {
                $ameliaPackage = AmeliaPackageModel::on('wordpress')->find($ameliaAppointment->packageId);
                if ($ameliaPackage) {
                    $package = $this->syncPackage($ameliaPackage);
                }
            }
        }

        $locationId = $ameliaAppointment->locationId
            ? \App\Models\Location::query()->where('amelia_location_id', $ameliaAppointment->locationId)->value('id')
            : null;

        $existing = AppointmentModel::query()
            ->where('amelia_appointment_id', $ameliaAppointment->id)
            ->first();

        if ($existing) {
            $existing->update([
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'package_id' => $package?->id,
                'location_id' => $locationId,
                'booking_start' => $ameliaAppointment->bookingStart,
                'booking_end' => $ameliaAppointment->bookingEnd,
                'status' => $this->mapAppointmentStatus($ameliaAppointment->status),
                'internal_notes' => $ameliaAppointment->internalNotes,
            ]);

            return $existing;
        }

        return AppointmentModel::query()->create([
            'amelia_appointment_id' => $ameliaAppointment->id,
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'package_id' => $package?->id,
            'location_id' => $locationId,
            'booking_start' => $ameliaAppointment->bookingStart,
            'booking_end' => $ameliaAppointment->bookingEnd,
            'status' => $this->mapAppointmentStatus($ameliaAppointment->status),
            'internal_notes' => $ameliaAppointment->internalNotes,
        ]);
    }

    /**
     * Sync an Amelia customer booking to Laravel Booking model
     */
    public function syncBooking(AmeliaCustomerBookingModel $ameliaBooking): ?BookingModel
    {
        // Sync customer first
        $customer = Customer::query()->where('amelia_user_id', $ameliaBooking->customerId)->first();
        if (! $customer) {
            $ameliaCustomer = AmeliaUserModel::on('wordpress')->find($ameliaBooking->customerId);
            if ($ameliaCustomer) {
                $customer = $this->syncCustomer($ameliaCustomer);
            }
        }

        if (! $customer) {
            return null;
        }

        // Sync appointment
        $appointment = null;
        if ($ameliaBooking->appointmentId) {
            $appointment = AppointmentModel::query()
                ->where('amelia_appointment_id', $ameliaBooking->appointmentId)
                ->first();

            if (! $appointment) {
                $ameliaAppointment = AmeliaAppointmentModel::on('wordpress')
                    ->find($ameliaBooking->appointmentId);
                if ($ameliaAppointment) {
                    $appointment = $this->syncAppointment($ameliaAppointment);
                }
            }
        }

        $existing = BookingModel::query()
            ->where('amelia_customer_booking_id', $ameliaBooking->id)
            ->first();

        if ($existing) {
            $existing->update([
                'appointment_id' => $appointment?->id,
                'customer_id' => $customer->id,
                'service_id' => $appointment?->service_id,
                'provider_id' => $appointment?->provider_id,
                'package_id' => $appointment?->package_id,
                'location_id' => $appointment?->location_id,
                'status' => $this->mapBookingStatus($ameliaBooking->status),
                'total_amount' => (float) ($ameliaBooking->price ?? $existing->total_amount ?? 0),
                'party_size' => (int) ($ameliaBooking->persons ?? $existing->party_size ?? 1),
                'booked_at' => $ameliaBooking->created ?? $appointment?->booking_start ?? $existing->booked_at ?? now(),
            ]);

            return $existing;
        }

        $bookedAt = $ameliaBooking->created ?? $appointment?->booking_start ?? now();

        return BookingModel::query()->create([
            'amelia_customer_booking_id' => $ameliaBooking->id,
            'appointment_id' => $appointment?->id,
            'customer_id' => $customer->id,
            'service_id' => $appointment?->service_id,
            'provider_id' => $appointment?->provider_id,
            'package_id' => $appointment?->package_id,
            'location_id' => $appointment?->location_id,
            'status' => $this->mapBookingStatus($ameliaBooking->status),
            'payment_status' => 'pending',
            'party_size' => (int) ($ameliaBooking->persons ?? 1),
            'total_amount' => (float) ($ameliaBooking->price ?? 0),
            'deposit_amount' => 0,
            'balance_amount' => (float) ($ameliaBooking->price ?? 0),
            'currency' => 'USD',
            'channel' => 'amelia_sync',
            'booked_at' => $bookedAt,
        ]);
    }

    /**
     * Sync an Amelia payment to Laravel Payment model
     */
    public function syncPayment(AmeliaPaymentModel $ameliaPayment): ?PaymentModel
    {
        // Find the booking
        $booking = BookingModel::query()
            ->where('amelia_customer_booking_id', $ameliaPayment->customerBookingId)
            ->first();

        if (! $booking) {
            // Try to sync the booking first
            $ameliaBooking = AmeliaCustomerBookingModel::on('wordpress')
                ->find($ameliaPayment->customerBookingId);
            if ($ameliaBooking) {
                $booking = $this->syncBooking($ameliaBooking);
            }
        }

        if (! $booking) {
            return null;
        }

        $existing = PaymentModel::query()
            ->where('booking_id', $booking->id)
            ->where('provider_reference', (string) $ameliaPayment->id)
            ->first();

        if ($existing) {
            $existing->update([
                'status' => $this->mapPaymentStatus($ameliaPayment->status),
                'amount' => (float) ($ameliaPayment->amount ?? $existing->amount ?? 0),
                'paid_at' => $ameliaPayment->dateTime ?? $existing->paid_at,
            ]);

            return $existing;
        }

        return PaymentModel::query()->create([
            'booking_id' => $booking->id,
            'provider' => $this->mapPaymentProvider($ameliaPayment->gateway),
            'provider_reference' => (string) $ameliaPayment->id,
            'status' => $this->mapPaymentStatus($ameliaPayment->status),
            'amount' => (float) ($ameliaPayment->amount ?? 0),
            'currency' => $ameliaPayment->currency ?? 'USD',
            'paid_at' => $ameliaPayment->dateTime ?? now(),
        ]);
    }

    /**
     * Sync service-staff relationship
     */
    public function syncServiceStaff(int $ameliaUserId, int $ameliaServiceId): void
    {
        $staff = StaffModel::query()->where('amelia_user_id', $ameliaUserId)->first();
        $service = ServiceModel::query()->where('amelia_service_id', $ameliaServiceId)->first();

        if (! $staff || ! $service) {
            return;
        }

        $exists = DB::table('service_staff')
            ->where('service_id', $service->id)
            ->where('staff_id', $staff->id)
            ->exists();

        if (! $exists) {
            DB::table('service_staff')->insert([
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Sync package-service relationship
     */
    public function syncPackageService(int $ameliaPackageId, int $ameliaServiceId, int $quantity = 1): void
    {
        $package = PackageModel::query()->where('amelia_package_id', $ameliaPackageId)->first();
        $service = ServiceModel::query()->where('amelia_service_id', $ameliaServiceId)->first();

        if (! $package || ! $service) {
            return;
        }

        $exists = DB::table('package_service')
            ->where('package_id', $package->id)
            ->where('service_id', $service->id)
            ->whereNull('provider_id')
            ->whereNull('location_id')
            ->exists();

        if (! $exists) {
            DB::table('package_service')->insert([
                'package_id' => $package->id,
                'service_id' => $service->id,
                'provider_id' => null,
                'location_id' => null,
                'quantity' => $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('package_service')
                ->where('package_id', $package->id)
                ->where('service_id', $service->id)
                ->whereNull('provider_id')
                ->whereNull('location_id')
                ->update(['quantity' => $quantity, 'updated_at' => now()]);
        }
    }

    private function mapAppointmentStatus(?string $status): string
    {
        return match ($status) {
            'canceled', 'rejected' => 'canceled',
            'pending' => 'pending',
            default => 'approved',
        };
    }

    private function mapBookingStatus(?string $status): string
    {
        return match ($status) {
            'canceled', 'rejected' => 'cancelled',
            'approved' => 'confirmed',
            'pending' => 'pending',
            default => 'pending',
        };
    }

    private function mapPaymentStatus(?string $status): string
    {
        return match ($status) {
            'paid', 'completed' => 'paid',
            'pending' => 'pending',
            'refunded' => 'refunded',
            default => 'pending',
        };
    }

    private function mapPaymentProvider(?string $gateway): string
    {
        return match ($gateway) {
            'stripe', 'paypal', 'razorpay' => $gateway,
            default => 'on_site',
        };
    }
}
