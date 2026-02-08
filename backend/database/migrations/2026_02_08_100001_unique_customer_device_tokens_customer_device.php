<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Ensures (customer_id, device_id) is unique so updateOrCreate works correctly.
     */
    public function up(): void
    {
        // Backfill: treat null device_id as '' so we have a single "default" token per customer
        DB::table('customer_device_tokens')->whereNull('device_id')->update(['device_id' => '']);

        // Keep only one row per (customer_id, device_id); delete older duplicates
        $dupes = DB::table('customer_device_tokens')
            ->select('customer_id', 'device_id')
            ->groupBy('customer_id', 'device_id')
            ->havingRaw('count(*) > 1')
            ->get();
        foreach ($dupes as $row) {
            $keep = DB::table('customer_device_tokens')
                ->where('customer_id', $row->customer_id)
                ->where('device_id', $row->device_id)
                ->orderByDesc('updated_at')
                ->value('id');
            if ($keep !== null) {
                DB::table('customer_device_tokens')
                    ->where('customer_id', $row->customer_id)
                    ->where('device_id', $row->device_id)
                    ->where('id', '!=', $keep)
                    ->delete();
            }
        }

        Schema::table('customer_device_tokens', function (Blueprint $table) {
            $table->dropIndex(['customer_id', 'device_id']);
            $table->unique(['customer_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_device_tokens', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'device_id']);
            $table->index(['customer_id', 'device_id']);
        });
    }
};
