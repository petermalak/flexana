<?php

return [
    /**
     * WARNING:
     * Enabling write operations means Laravel will modify the same database tables
     * that WordPress + Amelia Booking uses. This can break the plugin if related
     * tables / settings are not updated correctly.
     */
    'enable_write' => env('AMELIA_ENABLE_WRITE', false),
];

