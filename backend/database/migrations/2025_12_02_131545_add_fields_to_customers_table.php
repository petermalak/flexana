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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('phone');
            $table->date('birthday')->nullable()->after('gender');
            $table->string('country_phone_iso', 2)->nullable()->after('birthday');
            $table->string('external_id')->nullable()->index()->after('country_phone_iso');
            $table->string('language', 10)->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['gender', 'birthday', 'country_phone_iso', 'external_id', 'language']);
        });
    }
};
