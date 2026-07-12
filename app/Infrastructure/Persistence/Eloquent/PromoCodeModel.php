<?php

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Promo\Enums\PromoApplicableType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'applicable_to',
    ];

    protected $casts = [
        'percent_discount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'applicable_to' => PromoApplicableType::class,
    ];

    protected $attributes = [
        'applicable_to' => PromoApplicableType::Both->value,
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(PaymentModel::class, 'promo_code_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemptionModel::class, 'promo_code_id');
    }

    public function appointments(): BelongsToMany
    {
        return $this->belongsToMany(
            AppointmentModel::class,
            'appointment_promo_code',
            'promo_code_id',
            'appointment_id',
        )->withTimestamps();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(
            BranchModel::class,
            'branch_promo_code',
            'promo_code_id',
            'branch_id',
        )->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(
            PackageModel::class,
            'package_promo_code',
            'promo_code_id',
            'package_id',
        )->withTimestamps();
    }

    public function isRestrictedToPackages(): bool
    {
        if ($this->relationLoaded('packages')) {
            return $this->packages->isNotEmpty();
        }

        return $this->packages()->exists();
    }

    public function appliesToPackage(?int $packageId): bool
    {
        if ($packageId === null || ! $this->isRestrictedToPackages()) {
            return true;
        }

        return $this->packages()->whereKey($packageId)->exists();
    }

    public function isRestrictedToBranches(): bool
    {
        if ($this->relationLoaded('branches')) {
            return $this->branches->isNotEmpty();
        }

        return $this->branches()->exists();
    }

    public function appliesToBranch(?int $branchId): bool
    {
        if ($branchId === null || ! $this->isRestrictedToBranches()) {
            return true;
        }

        return $this->branches()->whereKey($branchId)->exists();
    }

    public function isRestrictedToAppointments(): bool
    {
        if ($this->relationLoaded('appointments')) {
            return $this->appointments->isNotEmpty();
        }

        return $this->appointments()->exists();
    }

    public function appliesToAppointment(?int $appointmentId): bool
    {
        if ($appointmentId === null || ! $this->isRestrictedToAppointments()) {
            return true;
        }

        return $this->appointments()->whereKey($appointmentId)->exists();
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

    public static function resolveForCustomer(
        string $code,
        int $customerId,
        PromoApplicableType $context,
        ?int $appointmentId = null,
        ?int $branchId = null,
        ?int $packageId = null,
    ): ?self {
        $promo = static::findByCode($code);
        if (! $promo) {
            return null;
        }

        if ($promo->invalidReasonForCustomer($customerId, $context, $appointmentId, $branchId, $packageId) !== null) {
            return null;
        }

        return $promo;
    }

    /**
     * Resolve a provided promo code, or return why it cannot be used.
     * When invalid, the promo model is still returned (except not_found) for messaging.
     *
     * @return array{0: ?self, 1: ?string} [promo, reason]
     */
    public static function resolveOrInvalidReason(
        string $code,
        ?int $customerId,
        PromoApplicableType $context,
        ?int $appointmentId = null,
        ?int $branchId = null,
        ?int $packageId = null,
    ): array {
        $promo = static::findByCode($code);
        if (! $promo) {
            return [null, 'not_found'];
        }

        $reason = $promo->invalidReasonForCustomer(
            $customerId,
            $context,
            $appointmentId,
            $branchId,
            $packageId,
        );

        if ($reason !== null) {
            return [$promo, $reason];
        }

        return [$promo, null];
    }

    public static function messageForReason(?string $reason, ?self $promo = null): string
    {
        return match ($reason) {
            'not_found' => (string) config('promo.not_found_message', 'Promo code does not exist.'),
            'inactive' => (string) config('promo.inactive_message', 'This promo code is not active.'),
            'not_yet_valid' => (string) config('promo.not_yet_valid_message', 'This promo code is not yet valid.'),
            'expired' => (string) config('promo.expired_message', 'This promo code has expired.'),
            'usage_limit_reached' => (string) config(
                'promo.usage_limit_reached_message',
                'You have already used this promo code the maximum number of times.',
            ),
            'wrong_type' => (string) (
                ($promo?->applicable_to?->value !== null
                    ? (config('promo.wrong_type_messages')[$promo->applicable_to->value] ?? null)
                    : null)
                ?? 'This promo code is not valid for this purchase.'
            ),
            'wrong_appointment' => (string) config(
                'promo.wrong_appointment_message',
                'This promo code is not valid for the selected session.',
            ),
            'wrong_branch' => (string) config(
                'promo.wrong_branch_message',
                'This promo code is not valid for the selected branch.',
            ),
            'wrong_package' => (string) config(
                'promo.wrong_package_message',
                'This promo code is not valid for the selected package.',
            ),
            default => 'This promo code is not valid.',
        };
    }

    public function isApplicableFor(PromoApplicableType $context): bool
    {
        return $this->applicable_to === PromoApplicableType::Both
            || $this->applicable_to === $context;
    }

    /**
     * Why the code cannot be used for this customer, or null if valid.
     * Per-user usage limit replaces the old global usage_limit / used_count cap.
     *
     * @return 'inactive'|'not_yet_valid'|'expired'|'usage_limit_reached'|'wrong_type'|'wrong_appointment'|'wrong_branch'|'wrong_package'|null
     */
    public function invalidReasonForCustomer(
        ?int $customerId = null,
        ?PromoApplicableType $context = null,
        ?int $appointmentId = null,
        ?int $branchId = null,
        ?int $packageId = null,
    ): ?string {
        if (! $this->is_active) {
            return 'inactive';
        }

        if ($context !== null && ! $this->isApplicableFor($context)) {
            return 'wrong_type';
        }

        if (
            $appointmentId !== null
            && $context !== PromoApplicableType::Packages
            && ! $this->appliesToAppointment($appointmentId)
        ) {
            return 'wrong_appointment';
        }

        if (
            $packageId !== null
            && $context !== PromoApplicableType::DropIns
            && ! $this->appliesToPackage($packageId)
        ) {
            return 'wrong_package';
        }

        if ($this->isRestrictedToBranches()) {
            if ($branchId === null || ! $this->appliesToBranch($branchId)) {
                return 'wrong_branch';
            }
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
        if ($customerId !== null
            && $this->usage_limit_per_user !== null
            && $this->usage_limit_per_user > 0
            && $this->redemptionCountForCustomer($customerId) >= $this->usage_limit_per_user) {
            return 'usage_limit_reached';
        }

        return null;
    }

    public function isValidForCustomer(
        int $customerId,
        ?PromoApplicableType $context = null,
        ?int $appointmentId = null,
        ?int $branchId = null,
        ?int $packageId = null,
    ): bool {
        return $this->invalidReasonForCustomer($customerId, $context, $appointmentId, $branchId, $packageId) === null;
    }

    /**
     * Atomically record a redemption for this customer if still under per-user limit.
     * Increments global used_count for admin totals.
     *
     * Only re-checks active / dates / usage limit here. Branch, appointment, package, and
     * type restrictions must already be validated when the promo is applied (resolveOrInvalidReason).
     * Passing no branch into invalidReasonForCustomer would falsely reject branch-restricted codes.
     */
    public function incrementUsageIfAllowed(int $customerId): bool
    {
        return (bool) DB::transaction(function () use ($customerId): bool {
            static::query()->whereKey($this->id)->lockForUpdate()->first();
            $this->refresh();

            if ($this->redemptionBlockedReason($customerId) !== null) {
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

    /**
     * Reasons that should block recording a redemption after apply-time validation already passed.
     *
     * @return 'inactive'|'not_yet_valid'|'expired'|'usage_limit_reached'|null
     */
    public function redemptionBlockedReason(int $customerId): ?string
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
        if ($this->usage_limit_per_user !== null
            && $this->usage_limit_per_user > 0
            && $this->redemptionCountForCustomer($customerId) >= $this->usage_limit_per_user) {
            return 'usage_limit_reached';
        }

        return null;
    }
}
