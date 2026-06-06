<?php

namespace App\Console\Commands;

use App\Application\Bookings\SendClassReminderEmailsService;
use Illuminate\Console\Command;

class SendClassReminderEmailsCommand extends Command
{
    protected $signature = 'bookings:send-class-reminders {--dry-run : List matching bookings without sending email}';

    protected $description = 'Email customers a reminder one day before their booked class';

    public function handle(SendClassReminderEmailsService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! config('sessions.class_reminder_enabled', true)) {
            $this->warn('Class reminder emails are disabled (SESSIONS_CLASS_REMINDER_ENABLED=false).');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $bookings = $service->bookingsForTomorrow();
            $this->info('Bookings with class tomorrow: ' . $bookings->count());
            foreach ($bookings as $booking) {
                $customer = $booking->customer;
                $appointment = $booking->appointment;
                $email = $customer?->email ?: '(no email)';
                $start = $appointment?->booking_start?->toDateTimeString() ?? 'unknown';
                $this->line("  #{$booking->id} {$email} @ {$start}");
            }

            return self::SUCCESS;
        }

        $stats = $service->sendForTomorrow();

        $this->info("Class reminders sent: {$stats['sent']}");
        if ($stats['skipped_no_email'] > 0) {
            $this->line("Skipped (no customer email): {$stats['skipped_no_email']}");
        }
        if ($stats['failed'] > 0) {
            $this->error("Failed: {$stats['failed']}");
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
