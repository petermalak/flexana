<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SMS driver for verification codes (signup, phone change)
    |--------------------------------------------------------------------------
    | "log" = only log the code (local/testing).
    | "twilio" = send via Twilio (set TWILIO_* in .env).
    | "smsmisr" = send via SMS Misr Egypt (set SMSMISR_* in .env). Get credentials from https://smsmisr.com/Client/Settings and API from https://smsmisr.com/API
    */
    'driver' => env('SMS_DRIVER', 'log'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'), // E.164, e.g. +1234567890
    ],

    'smsmisr' => [
        'username' => env('SMSMISR_USERNAME'),
        'password' => env('SMSMISR_PASSWORD'),
        'sender_id' => env('SMSMISR_SENDER_ID'), // Your activated sender name in SMS Misr
        // As in their docs: https://smsmisr.com/api/SMS/?environment=2&username=...&...
        'environment' => env('SMSMISR_ENVIRONMENT', '2'), // e.g. 2 = production (confirm in SMS Misr docs)
        'language' => env('SMSMISR_LANGUAGE', '1'), // depends on SMS Misr docs (e.g. 1 = Arabic / 2 = English)
        'api_url' => env('SMSMISR_API_URL', 'https://smsmisr.com/api/SMS/'),
    ],
];
