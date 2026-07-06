<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('phone_country_code', 4)->nullable()->after('phone');
            $table->string('phone_national_number', 15)->nullable()->after('phone_country_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['phone_country_code', 'phone_national_number']);
        });
    }
};
