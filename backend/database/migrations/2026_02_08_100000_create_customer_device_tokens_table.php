<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Stores FCM (Firebase Cloud Messaging) device tokens for push notifications.
     * One customer can have multiple tokens (e.g. phone + tablet, or re-login updates token).
     */
    public function up(): void
    {
        Schema::create('customer_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('fcm_token', 500);
            $table->string('platform', 20)->nullable()->comment('android, ios');
            $table->string('device_id', 255)->nullable()->comment('App-generated stable ID to update same device');
            $table->timestamps();

            $table->index(['customer_id', 'device_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_device_tokens');
    }
};
