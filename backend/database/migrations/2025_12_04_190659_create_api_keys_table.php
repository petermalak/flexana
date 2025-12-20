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
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name')->comment('Human-readable name for the API key (e.g., "Frontend App", "Mobile App")');
            $table->string('key_id')->unique()->comment('Public key identifier (sent in X-API-Key header)');
            $table->text('secret_key')->comment('Secret key for HMAC signature generation (encrypted)');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip_address')->nullable();
            $table->json('allowed_ips')->nullable()->comment('Optional: Restrict to specific IP addresses');
            $table->timestamp('expires_at')->nullable()->comment('Optional: Key expiration date');
            $table->timestamps();
            
            $table->index('key_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};
