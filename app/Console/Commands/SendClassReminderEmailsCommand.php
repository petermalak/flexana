<?php

namespace App\Console\Commands;

use App\Application\Bookings\SendClassRemindersService;
use App\Support\ClassReminderSettings;
use Illuminate\Console\Command;

class SendClassReminderEmailsCommand extends Command
{
    protected $signature = 'bookings:send-class-reminders {--dry-run : List matching bookings without sending reminders} {--automatic : Only run when automatic schedule window matches admin settings}';

    protected $description = 'Send class reminders (email + Firebase push) one day before booked classes';

    public function handle(SendClassRemindersService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! ClassReminderSettings::autoEnabled()) {
            $this->warn('Automatic class reminders are disabled in admin (Session notifications).');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $bookings = $service->bookingsForTomorrow();
            $this->info('Bookings with class tomorrow: ' . $bookings->count());
            foreach ($bookings as $booking) {
                $customer = $booking->customer;
                $appointment = $booking->appointment;
                $email = $customer?->email ?: '(no email)';
                $devices = $customer?->deviceTokens?->count() ?? 0;
                $start = $appointment?->booking_start?->toDateTimeString() ?? 'unknown';
                $this->line("  #{$booking->id} {$email} devices={$devices} @ {$start}");
            }

            return self::SUCCESS;
        }

        $stats = $service->sendForTomorrow(
            dryRun: false,
            automaticOnly: (bool) $this->option('automatic'),
        );

        $this->info("Email reminders sent: {$stats['email_sent']}");
        $this->info("Push notifications sent: {$stats['push_sent']}");
        if ($stats['skipped_no_email'] > 0) {
            $this->line("Skipped email (no address): {$stats['skipped_no_email']}");
        }
        if ($stats['skipped_no_device'] > 0) {
            $this->line("Skipped push (no FCM token / Firebase not configured): {$stats['skipped_no_device']}");
        }
        if ($stats['email_failed'] > 0) {
            $this->error("Email failed: {$stats['email_failed']}");
        }
        if ($stats['push_failed'] > 0) {
            $this->error("Push failed: {$stats['push_failed']}");
        }

        return ($stats['email_failed'] + $stats['push_failed']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
