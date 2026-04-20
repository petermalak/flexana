<?php

return [
    // Paymob can be hosted under different domains depending on account/region.
    // `accept.paymobsolutions.com` is widely used in their examples.
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymobsolutions.com'),

    // Paymob dashboard API key (used to get auth token)
    'api_key' => env('PAYMOB_API_KEY', ''),

    // Integration ID for the card payment method you enabled in Paymob
    'integration_id' => (int) env('PAYMOB_INTEGRATION_ID', 0),

    // The iframe ID to redirect users to
    'iframe_id' => (int) env('PAYMOB_IFRAME_ID', 0),

    // Used to validate redirect/webhook HMAC (recommended)
    'hmac_secret' => env('PAYMOB_HMAC_SECRET', ''),

    // The currency to send to Paymob (Paymob expects amounts in cents)
    'currency' => env('PAYMOB_CURRENCY', 'EGP'),
];

