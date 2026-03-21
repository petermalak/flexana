<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_package_purchases', function (Blueprint $table): void {
            $table->boolean('expires_by_months_only')->default(false)->after('amelia_package_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('customer_package_purchases', function (Blueprint $table): void {
            $table->dropColumn('expires_by_months_only');
        });
    }
};
