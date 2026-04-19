<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public web schedule endpoint (/api/v1/web-sessions)
    |--------------------------------------------------------------------------
    |
    | This endpoint is intentionally public (no Bearer token) for website embedding.
    | Protect it with rate limiting and (optionally) an IP allowlist.
    |
    */
    'enabled' => env('WEB_SCHEDULE_ENABLED', true),

    // Comma-separated list of IPs/CIDRs allowed to call /api/v1/web-sessions.
    // Empty = allow all IPs (still subject to rate limiting).
    'allowed_ips' => env('WEB_SCHEDULE_ALLOWED_IPS', ''),

    // Per-minute throttle for the named limiter "web-sessions" (see AppServiceProvider).
    'per_minute' => (int) env('WEB_SCHEDULE_PER_MINUTE', 120),
];
