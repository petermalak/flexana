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
        Schema::table('phone_verification_codes', function (Blueprint $table): void {
            $table->string('purpose', 32)->default('signup')->after('code');
            $table->unsignedBigInteger('customer_id')->nullable()->after('purpose');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table): void {
            $table->dropColumn(['purpose', 'customer_id']);
        });
    }
};
