<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->string('category', 32)->default('home')->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn('category');
        });
    }
};
