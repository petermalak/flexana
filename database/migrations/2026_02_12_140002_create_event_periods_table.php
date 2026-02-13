<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->json('zoom_meeting')->nullable();
            $table->json('lesson_space')->nullable();
            $table->string('google_calendar_event_id')->nullable();
            $table->string('google_meet_url')->nullable();
            $table->string('outlook_calendar_event_id')->nullable();
            $table->string('microsoft_teams_url')->nullable();
            $table->string('apple_calendar_event_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_periods');
    }
};
