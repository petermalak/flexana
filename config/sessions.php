<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Schedule timezone (sessions list / booking windows)
    |--------------------------------------------------------------------------
    |
    | Used when comparing appointment booking_start to "now" for upcoming
    | sessions. Set this to match how booking_start is stored in MySQL
    | (typically UTC if Amelia stores UTC, or your studio local zone if
    | datetimes are wall-clock local). Defaults to APP_BUSINESS_TIMEZONE,
    | then APP_TIMEZONE.
    |
    */
    'schedule_timezone' => env('SESSIONS_SCHEDULE_TIMEZONE', config('app.business_timezone', config('app.timezone'))),

    /*
    |--------------------------------------------------------------------------
    | Class reminder emails (one calendar day before booking_start)
    |--------------------------------------------------------------------------
    |
    | Daily job runs at class_reminder_send_at in schedule_timezone. Customers
    | with a confirmed/pending session booking tomorrow receive a reminder email.
    |
    */
    'class_reminder_enabled' => env('SESSIONS_CLASS_REMINDER_ENABLED', true),

    'class_reminder_send_at' => env('SESSIONS_CLASS_REMINDER_SEND_AT', '09:00'),

];
