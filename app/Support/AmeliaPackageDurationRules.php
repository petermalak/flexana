<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Duration rules by Amelia/Firestore package id (subcollection doc id / packageId).
 * Used for expiresAt = purchase_date + duration and for packages:sync-amelia-duration-rules.
 */
final class AmeliaPackageDurationRules
{
    /**
     * Yoga: 5 / 10 / 15 sessions — 12 months.
     * Reformer: 5 / 10 sessions — 3 months.
     * Unlimited: 30 days / 3 months.
     *
     * @var array<int, array{months: int|null, days: int|null}>
     */
    public const BY_AMELIA_ID = [
        44 => ['months' => 12, 'days' => null],
        45 => ['months' => 12, 'days' => null],
        46 => ['months' => 12, 'days' => null],
        40 => ['months' => 3, 'days' => null],
        41 => ['months' => 3, 'days' => null],
        47 => ['months' => null, 'days' => 30],
        48 => ['months' => 3, 'days' => null],
    ];

    public static function expiresAtFromAmeliaId(int $ameliaId, Carbon $purchaseDate): ?Carbon
    {
        if (! isset(self::BY_AMELIA_ID[$ameliaId])) {
            return null;
        }
        $rule = self::BY_AMELIA_ID[$ameliaId];
        if ($rule['days'] !== null && (int) $rule['days'] > 0) {
            return $purchaseDate->copy()->addDays((int) $rule['days']);
        }
        if ($rule['months'] !== null && (int) $rule['months'] > 0) {
            return $purchaseDate->copy()->addMonths((int) $rule['months']);
        }

        return null;
    }
}
