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
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('instructor_id')->nullable()->after('category')->constrained('staff')->nullOnDelete();
            $table->foreignId('class_type_id')->nullable()->after('instructor_id')->constrained('class_types')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['instructor_id']);
            $table->dropForeign(['class_type_id']);
            $table->dropColumn(['instructor_id', 'class_type_id']);
        });
    }
};
