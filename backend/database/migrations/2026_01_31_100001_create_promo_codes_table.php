<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('name')->nullable();
            $table->unsignedDecimal('percent_discount', 5, 2)->default(0); // 0-100
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->unsignedInteger('usage_limit')->nullable(); // null = unlimited
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
