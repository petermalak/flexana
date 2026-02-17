<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_service_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('day_of_week')->nullable(); // 'monday', 'tuesday', etc. or null for daily
            $table->time('start_time');
            $table->time('end_time');
            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = ongoing
            $table->string('recurrence_type')->default('weekly'); // 'daily', 'weekly', 'monthly'
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable(); // For additional settings
            $table->timestamps();

            $table->index(['staff_id', 'service_id']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_service_schedules');
    }
};
