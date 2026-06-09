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
     * @param  bool  $expiresByMonthsOnly  New app purchases: only purchase_date + package_duration (months). Legacy rows (false)
     *                                    keep days, package expiry date, and Amelia id fallbacks.
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
            if (! $package) {
                return null;
            }
            $months = $package->package_duration ?? null;
            if ($months !== null && (int) $months > 0) {
                return $purchaseDate->copy()->addMonths((int) $months);
            }

            return null;
        }

        $ameliaId = $package?->amelia_package_id ?? $ameliaPackageIdFromPurchase;

        if ($package) {
            $days = $package->package_duration_days ?? null;
            if ($days !== null && (int) $days > 0) {
                return $purchaseDate->copy()->addDays((int) $days);
            }

            $months = $package->package_duration ?? null;
            if ($months !== null && (int) $months > 0) {
                return $purchaseDate->copy()->addMonths((int) $months);
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
