<?php

namespace App\Application\Bookings;

use App\Application\Notifications\FcmPushNotificationService;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Support\ClassReminderEmailText;
use App\Support\ClassReminderNotificationText;
use App\Support\ClassReminderSettings;
use App\Support\InternalNotificationMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class SendClassRemindersService
{
    public function __construct(
        private readonly FcmPushNotificationService $push,
    ) {
    }

    /**
     * @return array{email_sent: int, push_sent: int, skipped_no_email: int, skipped_no_device: int, email_failed: int, push_failed: int}
     */
    public function sendForTomorrow(bool $dryRun = false, bool $automaticOnly = false): array
    {
        if ($automaticOnly && ! ClassReminderSettings::shouldRunAutomaticNow()) {
            return $this->emptyStats();
        }

        if (! ClassReminderSettings::autoEnabled()) {
            return $this->emptyStats();
        }

        $stats = $this->emptyStats();
        $emailEnabled = ClassReminderSettings::emailEnabled();
        $pushEnabled = ClassReminderSettings::pushEnabled();

        foreach ($this->bookingsForTomorrow() as $booking) {
            $customer = $booking->customer;
            $appointment = $booking->appointment;
            if (! $customer || ! $appointment) {
                continue;
            }

            if ($emailEnabled && $booking->class_reminder_sent_at === null) {
                if (! $customer->email) {
                    $stats['skipped_no_email']++;
                } elseif ($dryRun) {
                    $stats['email_sent']++;
                } else {
                    try {
                        InternalNotificationMail::sendCustomerAndInternalCopy(
                            ClassReminderEmailText::body($booking, $appointment, $customer),
                            ClassReminderEmailText::subject(),
                            $customer->email,
                            trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                        );
                        $booking->forceFill(['class_reminder_sent_at' => now()])->saveQuietly();
                        $stats['email_sent']++;
                    } catch (\Throwable $e) {
                        $stats['email_failed']++;
                        Log::error('Class reminder email failed', [
                            'booking_id' => $booking->id,
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            }

            if ($pushEnabled && $booking->class_reminder_push_sent_at === null) {
                if (! $this->push->isConfigured()) {
                    $stats['skipped_no_device']++;
                } elseif ($dryRun) {
                    $hasTokens = $customer->deviceTokens()
                        ->whereNotNull('fcm_token')
                        ->where('fcm_token', '!=', '')
                        ->exists();
                    if ($hasTokens) {
                        $stats['push_sent']++;
                    } else {
                        $stats['skipped_no_device']++;
                    }
                } else {
                    try {
                        $devicesNotified = $this->push->sendToCustomer(
                            (int) $customer->id,
                            ClassReminderNotificationText::pushTitle(),
                            ClassReminderNotificationText::pushBody($booking, $appointment, $customer),
                            ClassReminderNotificationText::pushData($booking, $appointment),
                        );

                        if ($devicesNotified > 0) {
                            $booking->forceFill(['class_reminder_push_sent_at' => now()])->saveQuietly();
                            $stats['push_sent']++;
                        } else {
                            $stats['skipped_no_device']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['push_failed']++;
                        Log::error('Class reminder push failed', [
                            'booking_id' => $booking->id,
                            'customer_id' => $customer->id,
                            'error' => $e->getMessage(),
                        ]);
                        report($e);
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * @return Collection<int, BookingModel>
     */
    public function bookingsForTomorrow(): Collection
    {
        $tz = (string) config('sessions.schedule_timezone', config('app.business_timezone', config('app.timezone')));
        $tomorrow = Carbon::now($tz)->addDay();
        $start = $tomorrow->copy()->startOfDay();
        $end = $tomorrow->copy()->endOfDay();

        return BookingModel::query()
            ->with(['customer.deviceTokens', 'appointment.service', 'appointment.provider'])
            ->whereNotNull('appointment_id')
            ->whereNull('cancelled_at')
            ->whereIn('status', ['confirmed', 'pending'])
            ->where(function ($query): void {
                $query->whereNull('class_reminder_sent_at')
                    ->orWhereNull('class_reminder_push_sent_at');
            })
            ->whereHas('appointment', function ($query) use ($start, $end): void {
                $query->whereBetween('booking_start', [$start, $end]);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{email_sent: int, push_sent: int, skipped_no_email: int, skipped_no_device: int, email_failed: int, push_failed: int}
     */
    private function emptyStats(): array
    {
        return [
            'email_sent' => 0,
            'push_sent' => 0,
            'skipped_no_email' => 0,
            'skipped_no_device' => 0,
            'email_failed' => 0,
            'push_failed' => 0,
        ];
    }
}
