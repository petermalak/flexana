<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use Illuminate\Support\Carbon;

final class PackagePurchaseLifecycle
{
    public static function afterSessionsConsumed(CustomerPackagePurchaseModel $purchase): void
    {
        $purchase->refresh();

        if ((int) $purchase->remaining_sessions <= 0 && (string) $purchase->status === 'active') {
            $purchase->update([
                'status' => 'expired',
                'remaining_sessions' => 0,
            ]);
        }
    }

    /**
     * When a cancelled booking refunds sessions, a depleted purchase may need reactivation.
     */
    public static function afterSessionsRestored(CustomerPackagePurchaseModel $purchase): void
    {
        $purchase->refresh();

        if ((int) $purchase->remaining_sessions <= 0) {
            return;
        }

        if ((string) $purchase->status !== 'expired') {
            return;
        }

        $purchase->loadMissing('package');
        $bizTz = (string) config('app.business_timezone');
        $expiresAt = PackagePurchaseExpiry::expiresAt(
            $purchase->package,
            $purchase->purchase_date,
            $purchase->amelia_package_id,
            (bool) $purchase->expires_by_months_only,
        );

        $today = Carbon::now($bizTz)->startOfDay();
        if ($expiresAt !== null && $expiresAt->copy()->timezone($bizTz)->startOfDay()->lt($today)) {
            return;
        }

        $purchase->update(['status' => 'active']);
    }
}
