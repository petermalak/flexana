<?php

namespace App\Application\Bookings;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Support\ApiDateTime;
use App\Support\InternalNotificationMail;
use App\Support\PackagePurchaseExpiry;
use App\Support\PackagePurchaseLifecycle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class CancelSessionBookingService
{
    public function cancel(
        BookingModel $booking,
        bool $enforceCancellationDeadline = true,
        bool $sendEmail = true,
    ): BookingModel {
        if ($booking->appointment_id === null) {
            throw new \RuntimeException('This is not a session booking.');
        }

        if (! in_array($booking->status, ['confirmed', 'pending'], true)) {
            throw new \RuntimeException('Only confirmed or pending bookings can be cancelled.');
        }

        $appointment = AppointmentModel::query()
            ->with(['service', 'provider'])
            ->find($booking->appointment_id);

        if (! $appointment) {
            throw new \RuntimeException('Session not found.');
        }

        if ($enforceCancellationDeadline) {
            $canCancelUntil = $this->cancellationDeadlineForAppointment($appointment);
            if (Carbon::now()->gt($canCancelUntil)) {
                throw new \RuntimeException('Cancellation deadline has passed.');
            }
        }

        DB::beginTransaction();
        try {
            $creditedDropIn = false;

            if ((bool) $booking->is_drop_in) {
                $paidStatuses = ['paid', 'completed'];
                if (in_array((string) $booking->payment_status, $paidStatuses, true)) {
                    $service = $appointment->service;
                    $serviceType = $this->sessionCategoryFromService($service) ?? 'Yoga';
                    $packageTitle = "Drop-in Credit - {$serviceType}";

                    $creditPackage = PackageModel::query()
                        ->where('status', 'active')
                        ->where('title', $packageTitle)
                        ->where('service_type', $serviceType)
                        ->first();

                    if (! $creditPackage) {
                        $creditPackage = PackageModel::query()->create([
                            'title' => $packageTitle,
                            'description' => 'Auto-generated credit created when a paid drop-in booking is cancelled.',
                            'service_type' => $serviceType,
                            'total_sessions' => (int) $booking->party_size,
                            'discount' => 0,
                            'price' => 0,
                            'package_duration' => 3,
                            'status' => 'active',
                        ]);
                    }

                    CustomerPackagePurchaseModel::query()->create([
                        'customer_id' => $booking->customer_id,
                        'package_id' => $creditPackage->id,
                        'total_sessions' => (int) $booking->party_size,
                        'remaining_sessions' => (int) $booking->party_size,
                        'purchase_date' => now(),
                        'status' => 'active',
                        'expires_by_months_only' => true,
                    ]);
                    $creditedDropIn = true;
                }
            }

            if (! $creditedDropIn && ! $booking->is_drop_in && $booking->package_id) {
                $purchase = null;
                if ($booking->customer_package_purchase_id) {
                    $purchase = CustomerPackagePurchaseModel::query()->find($booking->customer_package_purchase_id);
                }
                if (! $purchase) {
                    $purchase = CustomerPackagePurchaseModel::query()
                        ->where('customer_id', $booking->customer_id)
                        ->where('package_id', $booking->package_id)
                        ->orderBy('purchase_date')
                        ->first();
                }
                if ($purchase) {
                    $purchase->increment('remaining_sessions', (int) $booking->party_size);
                    PackagePurchaseLifecycle::afterSessionsRestored($purchase);
                }
            }

            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if ($sendEmail) {
            try {
                $this->sendBookingCancelledEmail($booking->fresh(['customer']), $appointment);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $booking->fresh();
    }

    public function cancellationDeadlineForAppointment(AppointmentModel $appointment): Carbon
    {
        $service = $appointment->service;
        $minutesBeforeCancellation = (int) ($service?->time_before ?? 0);

        return Carbon::parse($appointment->booking_start)->subMinutes($minutesBeforeCancellation);
    }

    public function customerCanCancelBooking(BookingModel $booking, ?AppointmentModel $appointment): bool
    {
        if (! $appointment || ! $appointment->booking_start) {
            return false;
        }
        if (! in_array($booking->status, ['confirmed', 'pending'], true)) {
            return false;
        }

        return Carbon::now()->lte($this->cancellationDeadlineForAppointment($appointment));
    }

    private function sendBookingCancelledEmail(BookingModel $booking, AppointmentModel $appointment): void
    {
        $customer = $booking->customer;
        if (! $customer || ! $customer->email) {
            return;
        }

        $service = $appointment->service;

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDateTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d H:i');

        $body = "Dear {$customerName},\n"
            . "Phone {$customer->phone}\n"
            . 'Your ' . ($service?->name ?? 'session') . " appointment, scheduled on {$appointmentDateTime} has been canceled.\n"
            . "Thank you for choosing our company,\n"
            . 'Flexana Team';

        $subject = 'Your Flexana booking has been cancelled';

        InternalNotificationMail::sendCustomerAndInternalCopy(
            $body,
            $subject,
            $customer->email,
            $customerName,
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
}
