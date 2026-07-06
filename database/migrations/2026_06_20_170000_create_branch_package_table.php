<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_package', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'package_id']);
        });

        $branchIds = DB::table('branches')->where('is_active', true)->pluck('id');
        $packageIds = DB::table('packages')->pluck('id');
        $now = now();

        foreach ($packageIds as $packageId) {
            foreach ($branchIds as $branchId) {
                DB::table('branch_package')->insert([
                    'branch_id' => $branchId,
                    'package_id' => $packageId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_package');
    }
};
