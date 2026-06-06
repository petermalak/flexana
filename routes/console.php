<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('packages:repair-amelia-durations {--dry-run : Show changes without saving} {--skip-backfill : Skip backfilling amelia_package_id on purchases}', function () {
    return $this->call('packages:sync-amelia-duration-rules', [
        '--dry-run' => $this->option('dry-run'),
        '--skip-backfill' => $this->option('skip-backfill'),
    ]);
})->purpose('Alias: fix wrong Yoga/Reformer months from an earlier sync — updates packages and backfills purchase amelia ids (same as packages:sync-amelia-duration-rules).');

Schedule::command('bookings:send-class-reminders')
    ->dailyAt((string) config('sessions.class_reminder_send_at', '09:00'))
    ->timezone((string) config('sessions.schedule_timezone', config('app.business_timezone', config('app.timezone'))))
    ->when(fn (): bool => (bool) config('sessions.class_reminder_enabled', true));
