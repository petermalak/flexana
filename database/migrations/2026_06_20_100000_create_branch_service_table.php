<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'service_id']);
        });

        $branchIds = DB::table('branches')->where('is_active', true)->pluck('id');
        $serviceIds = DB::table('services')->pluck('id');
        $now = now();

        foreach ($serviceIds as $serviceId) {
            foreach ($branchIds as $branchId) {
                DB::table('branch_service')->insert([
                    'branch_id' => $branchId,
                    'service_id' => $serviceId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $defaultBranchId = DB::table('branches')
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');

        if ($defaultBranchId !== null) {
            DB::table('appointments')
                ->whereNull('branch_id')
                ->update(['branch_id' => $defaultBranchId]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_service');
    }
};
