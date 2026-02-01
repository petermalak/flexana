<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds fields required for mobile auth (Sanctum + SMS verification) and Firestore migration.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('uid')->nullable()->unique()->after('uuid');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('password')->nullable()->after('phone_verified_at');
            $table->timestamp('email_verified_at')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['uid', 'phone_verified_at', 'password', 'email_verified_at']);
        });
    }
};
