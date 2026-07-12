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

    'applicable_types' => [
        'both' => 'Both',
        'packages' => 'Packages only',
        'drop_ins' => 'Drop-ins only',
    ],

    'applicable_type_descriptions' => [
        'both' => 'Works on package purchases and drop-in session bookings.',
        'packages' => 'Only when a customer buys a package (mobile purchase-package).',
        'drop_ins' => 'Only when a customer books a drop-in session (mobile, web Paymob, admin).',
    ],

    'wrong_type_messages' => [
        'packages' => 'This promo code is only valid for package purchases.',
        'drop_ins' => 'This promo code is only valid for drop-in bookings.',
    ],

    'wrong_appointment_message' => 'This promo code is not valid for the selected session.',

    'wrong_branch_message' => 'This promo code is not valid for the selected branch.',

    'wrong_package_message' => 'This promo code is not valid for the selected package.',

    'usage_limit_reached_message' => 'You have already used this promo code the maximum number of times.',

    'not_found_message' => 'Promo code does not exist.',

    'inactive_message' => 'This promo code is not active.',

    'not_yet_valid_message' => 'This promo code is not yet valid.',

    'expired_message' => 'This promo code has expired.',

];
