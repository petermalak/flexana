<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('class_reminder_sent_at')
                ->nullable()
                ->after('cancelled_at')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex(['class_reminder_sent_at']);
            $table->dropColumn('class_reminder_sent_at');
        });
    }
};
