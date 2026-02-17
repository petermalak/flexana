<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_off_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable(); // 'vacation', 'sick', 'personal', 'holiday', etc.
            $table->text('notes')->nullable();
            $table->boolean('is_all_day')->default(true);
            $table->time('start_time')->nullable(); // If not all day, specific time range
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'date']);
            $table->index(['staff_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_off_days');
    }
};
