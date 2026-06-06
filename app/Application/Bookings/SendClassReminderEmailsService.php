<?php

namespace App\Application\Bookings;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Support\ClassReminderEmailText;
use App\Support\InternalNotificationMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class SendClassReminderEmailsService
{
    /**
     * @return array{sent: int, skipped_no_email: int, failed: int}
     */
    public function sendForTomorrow(bool $dryRun = false): array
    {
        if (! config('sessions.class_reminder_enabled', true)) {
            return ['sent' => 0, 'skipped_no_email' => 0, 'failed' => 0];
        }

        $stats = ['sent' => 0, 'skipped_no_email' => 0, 'failed' => 0];

        foreach ($this->bookingsForTomorrow() as $booking) {
            $customer = $booking->customer;
            if (! $customer || ! $customer->email) {
                $stats['skipped_no_email']++;
                continue;
            }

            $appointment = $booking->appointment;
            if (! $appointment) {
                continue;
            }

            if ($dryRun) {
                $stats['sent']++;
                continue;
            }

            try {
                InternalNotificationMail::sendCustomerAndInternalCopy(
                    ClassReminderEmailText::body($booking, $appointment, $customer),
                    ClassReminderEmailText::subject(),
                    $customer->email,
                    trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                );

                $booking->forceFill(['class_reminder_sent_at' => now()])->saveQuietly();
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                Log::error('Class reminder email failed', [
                    'booking_id' => $booking->id,
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
                report($e);
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
            ->with(['customer', 'appointment.service', 'appointment.provider'])
            ->whereNotNull('appointment_id')
            ->whereNull('class_reminder_sent_at')
            ->whereNull('cancelled_at')
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereHas('appointment', function ($query) use ($start, $end): void {
                $query->whereBetween('booking_start', [$start, $end]);
            })
            ->orderBy('id')
            ->get();
    }
}
