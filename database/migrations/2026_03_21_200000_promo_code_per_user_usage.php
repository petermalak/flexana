<?php

use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->unsignedInteger('usage_limit_per_user')->nullable()->after('usage_limit');
        });

        foreach (PromoCodeModel::query()->cursor() as $promo) {
            if ($promo->usage_limit_per_user === null && $promo->usage_limit !== null) {
                $promo->usage_limit_per_user = $promo->usage_limit;
                $promo->saveQuietly();
            }
        }

        Schema::create('promo_code_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['promo_code_id', 'customer_id']);
        });

        if (Schema::hasTable('payments') && Schema::hasTable('bookings')) {
            $payments = DB::table('payments')
                ->join('bookings', 'payments.booking_id', '=', 'bookings.id')
                ->whereNotNull('payments.promo_code_id')
                ->select([
                    'payments.promo_code_id',
                    'bookings.customer_id',
                    'payments.created_at',
                ])
                ->orderBy('payments.id')
                ->get();

            foreach ($payments as $row) {
                DB::table('promo_code_redemptions')->insert([
                    'promo_code_id' => $row->promo_code_id,
                    'customer_id' => $row->customer_id,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->created_at ?? now(),
                ]);
            }

            foreach (PromoCodeModel::query()->pluck('id') as $promoId) {
                $total = DB::table('promo_code_redemptions')->where('promo_code_id', $promoId)->count();
                DB::table('promo_codes')->where('id', $promoId)->update(['used_count' => $total]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_code_redemptions');

        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->dropColumn('usage_limit_per_user');
        });
    }
};
