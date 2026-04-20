<?php

return [
    /**
     * Comma-separated list of origins allowed to embed the booking page in an <iframe>.
     *
     * Example:
     * BOOKING_EMBED_FRAME_ANCESTORS="https://flexanaegypt.com,https://www.flexanaegypt.com"
     */
    'frame_ancestors' => env(
        'BOOKING_EMBED_FRAME_ANCESTORS',
        // Safe local defaults for XAMPP/Laravel dev servers (ports matter for CSP origins).
        'https://flexanaegypt.com,https://www.flexanaegypt.com,http://localhost,http://127.0.0.1,http://localhost:80,http://127.0.0.1:80,http://localhost:8080,http://127.0.0.1:8080,http://localhost:8000,http://127.0.0.1:8000'
    ),

    /**
     * Optional: pull brand colors from a public WordPress page by fetching its HTML/CSS server-side.
     *
     * Example (local):
     * BOOKING_PALETTE_SOURCE_URL="http://localhost/Flexana/"
     *
     * Example (prod):
     * BOOKING_PALETTE_SOURCE_URL="https://flexanaegypt.com/"
     */
    'palette_source_url' => env('BOOKING_PALETTE_SOURCE_URL', ''),

    /**
     * Comma-separated host allowlist for BOOKING_PALETTE_SOURCE_URL (SSRF protection).
     *
     * Example:
     * BOOKING_PALETTE_SOURCE_HOSTS="localhost,127.0.0.1,flexanaegypt.com,www.flexanaegypt.com"
     */
    'palette_source_hosts' => env(
        'BOOKING_PALETTE_SOURCE_HOSTS',
        'localhost,127.0.0.1,flexanaegypt.com,www.flexanaegypt.com'
    ),

    /**
     * Max CSS files to scan from discovered stylesheets (keeps requests bounded).
     */
    'palette_max_stylesheets' => (int) env('BOOKING_PALETTE_MAX_STYLESHEETS', 6),

    /**
     * After Paymob payment result, redirect user to this page (WordPress schedule page).
     * Use different URLs per environment (local vs production).
     */
    'payment_success_redirect_url' => env(
        'BOOKING_PAYMENT_SUCCESS_REDIRECT_URL',
        'https://flexanaegypt.com/schedule-yoga-classes-book-now-new-cairo/'
    ),
    'payment_failed_redirect_url' => env(
        'BOOKING_PAYMENT_FAILED_REDIRECT_URL',
        'https://flexanaegypt.com/schedule-yoga-classes-book-now-new-cairo/'
    ),
];
