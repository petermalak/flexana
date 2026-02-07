<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS driver for verification codes (signup, phone change)
    |--------------------------------------------------------------------------
    | "log" = only log the code (local/testing). "twilio" = send via Twilio when credentials are set.
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'), // E.164, e.g. +1234567890
    ],
];
