<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_message_logs', function (Blueprint $table): void {
            $table->id();

            // Canonical recipient MSISDN (E.164), e.g. +2012...
            $table->string('msisdn', 32)->index();

            // OTP / verification code (stored as requested for admin tracing)
            $table->string('verification_code', 16)->nullable()->index();

            // Reason: signup | password_reset | phone_change | api_otp | other
            $table->string('reason', 40)->index();

            // Which configured driver was used (smsmisr / twilio / log)
            $table->string('driver', 30)->nullable()->index();

            // Whether the provider request was accepted by our code (not delivery)
            $table->boolean('success')->nullable()->index();

            // Provider metadata (optional, best-effort)
            $table->string('provider_message_id', 64)->nullable()->index();
            $table->string('provider_code', 16)->nullable();
            $table->string('provider_cost', 16)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();
            $table->index(['created_at', 'msisdn']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_message_logs');
    }
};

