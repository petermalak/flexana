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
     */
    public static function expiresAt(?PackageModel $package, ?Carbon $purchaseDate): ?Carbon
    {
        if (! $package || ! $purchaseDate) {
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

        if ($package->expiry) {
            return Carbon::parse($package->expiry)->startOfDay();
        }

        return null;
    }
}
