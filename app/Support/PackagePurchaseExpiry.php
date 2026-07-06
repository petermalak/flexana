<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\PackageModel;
use Carbon\Carbon;

/**
 * Expiry for a purchase from package rules (Firestore / Amelia: months or days for unlimited).
 */
final class PackagePurchaseExpiry
{
    /**
     * End date for an active purchase, or null if cannot be determined.
     *
     * @param  int|null  $ameliaPackageIdFromPurchase  When set (e.g. from customer_package_purchases.amelia_package_id),
     *                                               used if the package row has no duration fields or package is missing.
     * @param  bool  $expiresByMonthsOnly  New app purchases: purchase_date + package_duration_days (if set) or
     *                                    package_duration months. Legacy rows (false) also use Amelia id fallbacks.
     */
    public static function expiresAt(
        ?PackageModel $package,
        ?Carbon $purchaseDate,
        ?int $ameliaPackageIdFromPurchase = null,
        bool $expiresByMonthsOnly = false,
    ): ?Carbon {
        if (! $purchaseDate) {
            return null;
        }

        if ($expiresByMonthsOnly) {
            return self::expiresAtFromPackageRules($package, $purchaseDate);
        }

        $ameliaId = $package?->amelia_package_id ?? $ameliaPackageIdFromPurchase;

        if ($package) {
            $fromRules = self::expiresAtFromPackageRules($package, $purchaseDate);
            if ($fromRules !== null) {
                return $fromRules;
            }

            if ($package->expiry) {
                return Carbon::parse($package->expiry)->startOfDay();
            }
        }

        if ($ameliaId !== null && (int) $ameliaId > 0) {
            return AmeliaPackageDurationRules::expiresAtFromAmeliaId((int) $ameliaId, $purchaseDate);
        }

        return null;
    }

    /**
     * New app purchases: package_duration_days (e.g. 30-day unlimited) wins over months.
     */
    private static function expiresAtFromPackageRules(?PackageModel $package, Carbon $purchaseDate): ?Carbon
    {
        if (! $package) {
            return null;
        }

        $days = $package->package_duration_days ?? null;
        if ($days !== null && (int) $days > 0) {
            return $purchaseDate->copy()->addDays((int) $days);
        }

        $months = $package->package_duration ?? null;
        if ($months !== null && (int) $months > 0) {
            return $purchaseDate->copy()->addMonths((int) $months);
        }

        return null;
    }

    /**
     * Whether a purchase expiry covers booking a session on the given date (inclusive of expiry day).
     */
    public static function coversSessionDate(?Carbon $expiresAt, Carbon $sessionDate, string $businessTimezone): bool
    {
        if ($expiresAt === null) {
            return true;
        }

        $sessionDay = $sessionDate->copy()->timezone($businessTimezone)->startOfDay();
        $expiryDay = $expiresAt->copy()->timezone($businessTimezone)->startOfDay();

        return $sessionDay->lte($expiryDay);
    }
}
