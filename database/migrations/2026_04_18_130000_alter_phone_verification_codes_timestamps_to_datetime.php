<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // DATETIME avoids MySQL TIMESTAMP timezone conversions that can break OTP expiry checks.
        Schema::table('phone_verification_codes', function (Blueprint $table): void {
            $table->dateTime('created_at')->change();
            $table->dateTime('expires_at')->change();
        });
    }

    public function down(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table): void {
            $table->timestamp('created_at')->useCurrent()->change();
            $table->timestamp('expires_at')->change();
        });
    }
};

