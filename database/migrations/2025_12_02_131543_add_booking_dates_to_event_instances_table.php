<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_instances', function (Blueprint $table) {
            $table->dateTimeTz('booking_open_date')->nullable()->after('ends_at');
            $table->dateTimeTz('booking_close_date')->nullable()->after('booking_open_date');
            $table->foreignId('instructor_id')->nullable()->after('booking_close_date')->constrained('staff')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('event_instances', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropColumn(['booking_open_date', 'booking_close_date', 'instructor_id']);
        });
    }
};
