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
     */
    public static function expiresAt(?PackageModel $package, ?Carbon $purchaseDate, ?int $ameliaPackageIdFromPurchase = null): ?Carbon
    {
        if (! $purchaseDate) {
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
}
