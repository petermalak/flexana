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
        if (Schema::hasColumn('packages', 'package_duration_date')) {
            Schema::table('packages', function (Blueprint $table) {
                $table->dropColumn('package_duration_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->date('package_duration_date')->nullable()->after('package_duration');
        });
    }
};
