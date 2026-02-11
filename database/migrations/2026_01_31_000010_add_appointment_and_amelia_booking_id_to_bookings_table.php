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
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('appointment_id')->nullable()->after('event_instance_id')->constrained('appointments')->nullOnDelete();
            $table->unsignedBigInteger('amelia_customer_booking_id')->nullable()->after('appointment_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['appointment_id']);
            $table->dropColumn(['appointment_id', 'amelia_customer_booking_id']);
        });
    }
};
