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

        // Twilio Verify API (v2): when set, send via Verifications and verify via VerificationCheck
        'verify_service_sid' => env('TWILIO_VERIFY_SERVICE_SID'), // e.g. VAc1a2ecd13b9ab2931b199c8eec1724b3

        // Channel can be: sms | whatsapp (used only when not using Verify API)
        'channel' => env('TWILIO_CHANNEL', 'sms'),

        // SMS sender (E.164), e.g. +14472515930
        'from' => env('TWILIO_FROM'),

        // WhatsApp sender (usually "whatsapp:+14155238886" or your WhatsApp-enabled number)
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),

        // For WhatsApp templates (Content API)
        'content_sid' => env('TWILIO_CONTENT_SID'), // e.g. HX229f5a04fd0510ce1b071852155d3e75
    ],

    'smsmisr' => [
        'username' => env('SMSMISR_USERNAME'),
        'password' => env('SMSMISR_PASSWORD'),
        // SMS Misr expects a *sender token* (Sender IDs), not the display name.
        // See https://smsmisr.com/API (Bulk SMS API).
        'sender_id' => env('SMSMISR_SENDER_ID'),
        // SMS Misr docs: 1 = Live, 2 = Test
        'environment' => env('SMSMISR_ENVIRONMENT', '1'),
        // SMS Misr docs: 1=Eng, 2=Arabic, 3=Unicode
        'language' => env('SMSMISR_LANGUAGE', '1'),
        // Bulk SMS endpoint base URL (trailing slash recommended): https://smsmisr.com/api/SMS/
        'api_url' => env('SMSMISR_API_URL', 'https://smsmisr.com/api/SMS/'),

        // OTP API (template-based, usually better deliverability for verification codes)
        // Base URL: https://smsmisr.com/api/OTP/
        // Template token must be created in SMS Misr console.
        'otp_api_url' => env('SMSMISR_OTP_API_URL', 'https://smsmisr.com/api/OTP/'),
        'otp_template' => env('SMSMISR_OTP_TEMPLATE'),
    ],
];
