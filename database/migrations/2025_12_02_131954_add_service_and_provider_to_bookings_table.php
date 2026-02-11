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
            $table->foreignId('service_id')->nullable()->after('package_id')->constrained('services')->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->after('service_id')->constrained('staff')->nullOnDelete();
            $table->unsignedBigInteger('location_id')->nullable()->after('provider_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['provider_id']);
            $table->dropColumn(['service_id', 'provider_id', 'location_id']);
        });
    }
};
