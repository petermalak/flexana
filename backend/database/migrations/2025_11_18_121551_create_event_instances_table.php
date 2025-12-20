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
        Schema::create('event_instances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Use a plain foreign key column here to avoid migration order issues in MySQL.
            // The events table is created in a separate migration with the same timestamp,
            // so MySQL was failing to add the foreign key constraint.
            $table->unsignedBigInteger('event_id');
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->unsignedInteger('capacity')->nullable();
            $table->string('location')->nullable();
            $table->json('resources')->nullable();
            $table->string('status')->default('scheduled');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_instances');
    }
};
