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
        Schema::dropIfExists('staff_service_schedules');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-create table only if you need to rollback (structure from original migration)
        Schema::create('staff_service_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('day_of_week')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('recurrence_type')->default('weekly');
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'service_id']);
            $table->index(['start_date', 'end_date']);
        });
    }
};
