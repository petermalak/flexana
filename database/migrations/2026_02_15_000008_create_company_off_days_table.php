<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_off_days', function (Blueprint $table): void {
            $table->id();
            $table->string('name'); // 'New Year', 'Christmas', 'Company Holiday', etc.
            $table->date('date');
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false); // Repeats yearly
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('date');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_off_days');
    }
};
