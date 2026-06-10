<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use Carbon\Carbon;

/**
 * Finds an active package purchase that can cover a session on a given date.
 */
final class ValidPackagePurchaseFinder
{
    /**
     * @param  callable(mixed): ?string  $packageCategoryResolver
     */
    public static function forSession(
        int $customerId,
        ?string $sessionCategory,
        int $persons,
        Carbon $sessionDate,
        callable $packageCategoryResolver,
        bool $preferOldestPurchase = true,
    ): ?CustomerPackagePurchaseModel {
        if ($sessionCategory === null) {
            return null;
        }

        $bizTz = (string) config('app.business_timezone');
        $query = CustomerPackagePurchaseModel::query()
            ->with(['package.services'])
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>=', $persons)
            ->whereNotNull('package_id');

        if ($preferOldestPurchase) {
            $query->orderBy('purchase_date');
        } else {
            $query->orderByDesc('purchase_date');
        }

        foreach ($query->get() as $purchase) {
            $package = $purchase->package;
            if (! $package) {
                continue;
            }
            if ($packageCategoryResolver($package) !== $sessionCategory) {
                continue;
            }
            $expiresAt = PackagePurchaseExpiry::expiresAt(
                $package,
                $purchase->purchase_date,
                $purchase->amelia_package_id,
                (bool) $purchase->expires_by_months_only,
            );
            if (! PackagePurchaseExpiry::coversSessionDate($expiresAt, $sessionDate, $bizTz)) {
                continue;
            }

            return $purchase;
        }

        return null;
    }
}
