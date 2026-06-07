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
    | Class reminders (one calendar day before booking_start)
    |--------------------------------------------------------------------------
    |
    | Daily job runs at class_reminder_send_at in schedule_timezone. Customers
    | with a confirmed/pending session booking tomorrow receive email and/or
    | Firebase push notification (requires FCM tokens + service account JSON).
    |
    */
    'class_reminder_enabled' => env('SESSIONS_CLASS_REMINDER_ENABLED', true),

    'class_reminder_email_enabled' => env('SESSIONS_CLASS_REMINDER_EMAIL_ENABLED', true),

    'class_reminder_push_enabled' => env('SESSIONS_CLASS_REMINDER_PUSH_ENABLED', true),

    'class_reminder_send_at' => env('SESSIONS_CLASS_REMINDER_SEND_AT', '09:00'),

];
