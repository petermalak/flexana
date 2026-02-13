<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('label');
            $table->string('type')->default('text'); // text, textarea, number, select, checkbox, etc.
            $table->string('entity_type')->default('booking'); // booking, customer, event, etc.
            $table->boolean('required')->default(false);
            $table->json('options')->nullable(); // for select: ["opt1","opt2"]
            $table->unsignedInteger('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
