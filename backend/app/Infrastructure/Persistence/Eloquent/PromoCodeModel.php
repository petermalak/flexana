<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->valid_from && now()->lt($this->valid_from)) {
            return false;
        }
        if ($this->valid_until && now()->gt($this->valid_until)) {
            return false;
        }
        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }
        return true;
    }
}
