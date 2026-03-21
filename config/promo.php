<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Promo validity calendar (valid_from / valid_until inclusive days)
    |--------------------------------------------------------------------------
    |
    | Day boundaries for "not yet valid" / "expired" use this timezone.
    | Defaults to APP_TIMEZONE (Africa/Cairo) so promo rules match the app.
    | Override with PROMO_CALENDAR_TIMEZONE if needed.
    |
    */

    'calendar_timezone' => env('PROMO_CALENDAR_TIMEZONE', config('app.timezone')),

];
