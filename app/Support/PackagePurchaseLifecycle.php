<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;

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
}
