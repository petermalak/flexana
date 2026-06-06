<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Existing promo codes worked on package purchases and drop-in bookings
     * before type separation; keep that behavior by defaulting them to "both".
     */
    public function up(): void
    {
        DB::table('promo_codes')->update(['applicable_to' => 'both']);
    }

    public function down(): void
    {
        DB::table('promo_codes')->update(['applicable_to' => 'drop_ins']);
    }
};
