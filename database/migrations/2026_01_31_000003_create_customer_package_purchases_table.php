<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_package_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->unsignedBigInteger('amelia_package_id')->nullable()->index();
            $table->unsignedInteger('total_sessions')->default(0);
            $table->unsignedInteger('remaining_sessions')->default(0);
            $table->timestamp('purchase_date');
            $table->string('status', 20)->default('active')->index();
            $table->unsignedBigInteger('amelia_package_customer_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_package_purchases');
    }
};
