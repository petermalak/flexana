<?php

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\BranchModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ApiKey extends Model
{

    protected $fillable = [
        'uuid',
        'name',
        'branch_id',
        'key_id',
        'secret_key',
        'is_active',
        'last_used_at',
        'last_ip_address',
        'allowed_ips',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'allowed_ips' => 'array',
    ];

    protected $hidden = [
        'secret_key',
    ];

    /**
     * Generate a new API key pair
     */
    public static function generate(
        string $name,
        ?array $allowedIps = null,
        ?\DateTime $expiresAt = null,
        ?int $branchId = null,
    ): array {
        $keyId = 'fk_' . Str::random(32);
        $secretKey = Str::random(64);

        $apiKey = self::create([
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'branch_id' => $branchId,
            'key_id' => $keyId,
            'secret_key' => Crypt::encryptString($secretKey), // Encrypt (not hash) so we can decrypt for HMAC
            'is_active' => true,
            'allowed_ips' => $allowedIps,
            'expires_at' => $expiresAt,
        ]);

        // Return both keys (secret is only shown once)
        return [
            'id' => $apiKey->id,
            'uuid' => $apiKey->uuid,
            'name' => $apiKey->name,
            'branch_id' => $apiKey->branch_id,
            'key_id' => $keyId,
            'secret_key' => $secretKey, // Only shown once!
            'created_at' => $apiKey->created_at,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(BranchModel::class, 'branch_id');
    }

    /**
     * Get the decrypted secret key for HMAC verification
     */
    public function getDecryptedSecret(): string
    {
        return Crypt::decryptString($this->secret_key);
    }

    /**
     * Check if the key is valid (active and not expired)
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if IP is allowed
     */
    public function isIpAllowed(?string $ip): bool
    {
        if (!$this->allowed_ips || empty($this->allowed_ips)) {
            return true; // No restriction
        }

        return in_array($ip, $this->allowed_ips);
    }

    /**
     * Update last used timestamp and IP
     */
    public function recordUsage(?string $ip = null): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_ip_address' => $ip,
        ]);
    }
}
