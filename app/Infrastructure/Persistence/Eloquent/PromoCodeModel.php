<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PromoCodeModel extends Model
{
    protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'name',
        'percent_discount',
        'valid_from',
        'valid_until',
        'usage_limit',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'percent_discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'promo_code_id');
    }

    /**
     * Find promo by code (trimmed, case-insensitive) so mobile input matches DB.
     */
    public static function findByCode(string $code): ?self
    {
        $trimmed = trim($code);
        if ($trimmed === '') {
            return null;
        }

        return static::query()
            ->whereRaw('LOWER(TRIM(code)) = ?', [Str::lower($trimmed)])
            ->first();
    }

    /**
     * Why the code cannot be used, or null if it is valid right now.
     * Uses full datetime precision (same instant as DB) for valid_from / valid_until.
     *
     * @return 'inactive'|'not_yet_valid'|'expired'|'usage_limit_reached'|null
     */
    public function invalidReason(): ?string
    {
        if (! $this->is_active) {
            return 'inactive';
        }
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return 'not_yet_valid';
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return 'expired';
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return 'usage_limit_reached';
        }

        return null;
    }

    public function isValid(): bool
    {
        return $this->invalidReason() === null;
    }

    /**
     * Atomically increment used_count only if under usage_limit (when set).
     * Returns false if limit already reached (e.g. race with another request).
     */
    public function incrementUsageIfAllowed(): bool
    {
        $query = static::query()->whereKey($this->id);

        $query->where(function ($q): void {
            $q->whereNull('usage_limit')
                ->orWhereColumn('used_count', '<', 'usage_limit');
        });

        return (bool) $query->increment('used_count');
    }
}
