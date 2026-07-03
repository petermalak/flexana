<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_promo_code', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['promo_code_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_promo_code');
    }
};
