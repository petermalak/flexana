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
    | datetimes are wall-clock local). Defaults to APP_TIMEZONE.
    |
    */
    'schedule_timezone' => env('SESSIONS_SCHEDULE_TIMEZONE', env('APP_TIMEZONE', 'UTC')),

];
