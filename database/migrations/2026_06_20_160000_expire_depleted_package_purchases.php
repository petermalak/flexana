<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('customer_package_purchases')
            ->where('status', 'active')
            ->where('remaining_sessions', '<=', 0)
            ->update([
                'status' => 'expired',
                'remaining_sessions' => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data cleanup is not reversible.
    }
};
