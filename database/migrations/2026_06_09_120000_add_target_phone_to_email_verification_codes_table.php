<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->string('target_phone', 32)->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table): void {
            $table->dropColumn('target_phone');
        });
    }
};
