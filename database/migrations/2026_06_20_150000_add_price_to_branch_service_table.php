<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_service', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable()->after('service_id');
        });

        $rows = DB::table('branch_service')
            ->join('services', 'services.id', '=', 'branch_service.service_id')
            ->select('branch_service.id', 'services.price')
            ->get();

        foreach ($rows as $row) {
            DB::table('branch_service')
                ->where('id', $row->id)
                ->update(['price' => $row->price]);
        }
    }

    public function down(): void
    {
        Schema::table('branch_service', function (Blueprint $table): void {
            $table->dropColumn('price');
        });
    }
};
