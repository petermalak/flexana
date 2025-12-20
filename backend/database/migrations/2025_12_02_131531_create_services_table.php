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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration')->default(1800); // in seconds
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('min_capacity')->default(1);
            $table->unsignedInteger('max_capacity')->default(1);
            $table->string('color_hex')->nullable();
            $table->string('status')->default('visible')->index(); // visible, hidden
            $table->string('picture_full_path')->nullable();
            $table->string('picture_thumb_path')->nullable();
            $table->json('extras')->nullable(); // Array of extra services
            $table->json('custom_pricing')->nullable(); // Custom pricing configuration
            $table->json('settings')->nullable(); // Service settings (payments, zoom, etc.)
            $table->json('gallery')->nullable(); // Array of gallery images
            $table->unsignedInteger('position')->default(0);
            $table->decimal('deposit', 10, 2)->default(0);
            $table->string('deposit_payment')->default('disabled'); // disabled, enabled
            $table->boolean('deposit_per_person')->default(true);
            $table->boolean('full_payment')->default(false);
            $table->boolean('bringing_anyone')->default(true);
            $table->boolean('show')->default(true);
            $table->boolean('aggregated_price')->default(true);
            $table->string('recurring_cycle')->default('disabled');
            $table->string('recurring_sub')->default('future');
            $table->unsignedInteger('recurring_payment')->default(0);
            $table->unsignedInteger('time_after')->nullable();
            $table->unsignedInteger('time_before')->nullable();
            $table->json('limit_per_customer')->nullable();
            $table->unsignedInteger('min_selected_extras')->nullable();
            $table->boolean('mandatory_extra')->default(false);
            $table->unsignedInteger('max_extra_people')->nullable();
            $table->string('category_id')->nullable(); // For compatibility with Emilia API
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
