<?php

return [
    /**
     * Comma-separated list of origins allowed to embed the booking page in an <iframe>.
     *
     * Example:
     * BOOKING_EMBED_FRAME_ANCESTORS="https://flexanaegypt.com,https://www.flexanaegypt.com"
     */
    'frame_ancestors' => env('BOOKING_EMBED_FRAME_ANCESTORS', 'https://flexanaegypt.com,https://www.flexanaegypt.com'),
];
