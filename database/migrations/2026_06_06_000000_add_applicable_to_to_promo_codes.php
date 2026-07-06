<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->string('applicable_to', 32)
                ->default('both')
                ->after('is_active')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->dropIndex(['applicable_to']);
            $table->dropColumn('applicable_to');
        });
    }
};
