<?php

namespace App\Application\Bookings;

use App\Application\Notifications\FcmPushNotificationService;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Support\ClassReminderEmailText;
use App\Support\ClassReminderNotificationText;
use App\Support\InternalNotificationMail;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class AdminSessionNotificationService
{
    public function __construct(
        private readonly FcmPushNotificationService $push,
    ) {
    }

    /**
     * @return array{push_sent: int, email_sent: int, skipped_no_device: int, skipped_no_email: int, push_failed: int, email_failed: int, bookings: int}
     */
    public function sendPushForAppointment(AppointmentModel $appointment): array
    {
        return $this->sendForAppointment($appointment, push: true, email: false);
    }

    /**
     * @return array{push_sent: int, email_sent: int, skipped_no_device: int, skipped_no_email: int, push_failed: int, email_failed: int, bookings: int}
     */
    public function sendEmailForAppointment(AppointmentModel $appointment): array
    {
        return $this->sendForAppointment($appointment, push: false, email: true);
    }

    /**
     * @return array{push_sent: int, email_sent: int, skipped_no_device: int, skipped_no_email: int, push_failed: int, email_failed: int, bookings: int}
     */
    public function sendPushAndEmailForAppointment(AppointmentModel $appointment): array
    {
        return $this->sendForAppointment($appointment, push: true, email: true);
    }

    /**
     * @return Collection<int, BookingModel>
     */
    public function activeBookingsForAppointment(AppointmentModel $appointment): Collection
    {
        return $appointment->bookings()
            ->with(['customer.deviceTokens', 'appointment.service', 'appointment.provider'])
            ->whereNull('cancelled_at')
            ->whereIn('status', ['confirmed', 'pending'])
            ->orderBy('id')
            ->get();
    }

    public function activeAttendeeCount(AppointmentModel $appointment): int
    {
        return (int) $this->activeBookingsForAppointment($appointment)->sum('party_size');
    }

    /**
     * @return array{push_sent: int, email_sent: int, skipped_no_device: int, skipped_no_email: int, push_failed: int, email_failed: int, bookings: int}
     */
    private function sendForAppointment(AppointmentModel $appointment, bool $push, bool $email): array
    {
        $stats = [
            'push_sent' => 0,
            'email_sent' => 0,
            'skipped_no_device' => 0,
            'skipped_no_email' => 0,
            'push_failed' => 0,
            'email_failed' => 0,
            'bookings' => 0,
        ];

        $appointment->loadMissing(['service', 'provider', 'branch']);

        foreach ($this->activeBookingsForAppointment($appointment) as $booking) {
            $stats['bookings']++;
            $customer = $booking->customer;
            if (! $customer) {
                continue;
            }

            if ($email) {
                if (! $customer->email) {
                    $stats['skipped_no_email']++;
                } else {
                    try {
                        InternalNotificationMail::sendCustomerAndInternalCopy(
                            ClassReminderEmailText::body($booking, $appointment, $customer),
                            ClassReminderEmailText::subject(),
                            $customer->email,
                            trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                        );
                        $stats['email_sent']++;
                    } catch (\Throwable $e) {
                        $stats['email_failed']++;
                        Log::error('Admin session reminder email failed', [
                            'appointment_id' => $appointment->id,
                            'booking_id' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            }

            if ($push) {
                if (! $this->push->isConfigured()) {
                    $stats['skipped_no_device']++;
                } else {
                    try {
                        $devices = $this->push->sendToCustomer(
                            (int) $customer->id,
                            ClassReminderNotificationText::pushTitle(),
                            ClassReminderNotificationText::pushBody($booking, $appointment, $customer),
                            ClassReminderNotificationText::pushData($booking, $appointment),
                        );
                        if ($devices > 0) {
                            $stats['push_sent']++;
                        } else {
                            $stats['skipped_no_device']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['push_failed']++;
                        Log::error('Admin session reminder push failed', [
                            'appointment_id' => $appointment->id,
                            'booking_id' => $booking->id,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            }
        }

        return $stats;
    }
}
