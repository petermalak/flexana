<?php

namespace App\Infrastructure\Persistence\Eloquent;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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
        'usage_limit_per_user',
        'used_count',
        'is_active',
    ];

    protected $casts = [
        'percent_discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'promo_code_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemptionModel::class, 'promo_code_id');
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
     * How many times this customer has redeemed this promo (recorded rows).
     */
    public function redemptionCountForCustomer(int $customerId): int
    {
        return (int) $this->redemptions()
            ->where('customer_id', $customerId)
            ->count();
    }

    /**
     * Why the code cannot be used for this customer, or null if valid.
     * Per-user usage limit replaces the old global usage_limit / used_count cap.
     *
     * @return 'inactive'|'not_yet_valid'|'expired'|'usage_limit_reached'|null
     */
    public function invalidReasonForCustomer(int $customerId): ?string
    {
        if (! $this->is_active) {
            return 'inactive';
        }

        $tz = (string) config('promo.calendar_timezone', 'UTC');

        if ($this->valid_from) {
            $fromStart = $this->valid_from->copy()->timezone($tz)->startOfDay();
            if (Carbon::now($tz)->lt($fromStart)) {
                return 'not_yet_valid';
            }
        }
        if ($this->valid_until) {
            $untilEnd = $this->valid_until->copy()->timezone($tz)->endOfDay();
            if (Carbon::now($tz)->gt($untilEnd)) {
                return 'expired';
            }
        }
        if ($this->usage_limit_per_user !== null && $this->usage_limit_per_user > 0) {
            if ($this->redemptionCountForCustomer($customerId) >= $this->usage_limit_per_user) {
                return 'usage_limit_reached';
            }
        }

        return null;
    }

    public function isValidForCustomer(int $customerId): bool
    {
        return $this->invalidReasonForCustomer($customerId) === null;
    }

    /**
     * Atomically record a redemption for this customer if still under per-user limit.
     * Increments global used_count for admin totals.
     */
    public function incrementUsageIfAllowed(int $customerId): bool
    {
        return (bool) DB::transaction(function () use ($customerId): bool {
            static::query()->whereKey($this->id)->lockForUpdate()->first();
            $this->refresh();

            if ($this->invalidReasonForCustomer($customerId) !== null) {
                return false;
            }

            PromoCodeRedemptionModel::query()->create([
                'promo_code_id' => $this->id,
                'customer_id' => $customerId,
            ]);

            $this->increment('used_count');

            return true;
        });
    }
}
