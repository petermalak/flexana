<?php

return [
    /**
     * Comma-separated list of origins allowed to embed the booking page in an <iframe>.
     *
     * Example:
     * BOOKING_EMBED_FRAME_ANCESTORS="https://flexanaegypt.com,https://www.flexanaegypt.com"
     */
    'frame_ancestors' => env('BOOKING_EMBED_FRAME_ANCESTORS', 'https://flexanaegypt.com,https://www.flexanaegypt.com'),

    /**
     * Comma-separated parent origins allowed to send palette updates via postMessage.
     *
     * Example:
     * BOOKING_EMBED_PALETTE_PARENTS="http://localhost,http://127.0.0.1,https://flexanaegypt.com,https://www.flexanaegypt.com"
     */
    'palette_parents' => env('BOOKING_EMBED_PALETTE_PARENTS', ''),
];
