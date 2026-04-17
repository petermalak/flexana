<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recalculate remaining sessions for package purchases from actual bookings usage.
 *
 * This is intended to fix synced/migrated data where `remaining_sessions` was set equal
 * to `total_sessions` even though the customer already consumed sessions.
 *
 * Rules (conservative):
 * - Count "used" sessions from bookings where:
 *   - customer_id matches
 *   - package_id matches the purchase's package_id
 *   - status in (confirmed, pending) and not cancelled
 *   - and booking is not a drop-in (is_drop_in = 0 or NULL)
 * - used_sessions = SUM(party_size) (defaults to 1)
 * - remaining_sessions = max(0, total_sessions - used_sessions)
 *
 * Note: This only works for purchases that have a non-null package_id.
 */
class RecalculatePackageRemainingSessionsCommand extends Command
{
    protected $signature = 'packages:recalculate-remaining-sessions
                            {--dry-run : Show changes without writing}
                            {--customer-id= : Limit to a single customer_id}
                            {--package-id= : Limit to a single package_id}
                            {--only-active : Only recalculate purchases with status=active (default true)}
                            {--since= : Only consider bookings booked_at >= YYYY-MM-DD (optional)}';

    protected $description = 'Recalculate customer_package_purchases.remaining_sessions from bookings usage (customer_id + package_id).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $customerId = $this->option('customer-id') !== null ? (int) $this->option('customer-id') : null;
        $packageId = $this->option('package-id') !== null ? (int) $this->option('package-id') : null;
        $onlyActive = $this->option('only-active') === null ? true : (bool) $this->option('only-active');
        $sinceRaw = $this->option('since');
        $since = null;
        if (is_string($sinceRaw) && trim($sinceRaw) !== '') {
            try {
                $since = Carbon::parse(trim($sinceRaw))->startOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --since date. Use YYYY-MM-DD.');
                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $purchaseQuery = CustomerPackagePurchaseModel::query()
            ->whereNotNull('package_id');

        if ($onlyActive) {
            $purchaseQuery->where('status', 'active');
        }
        if ($customerId !== null) {
            $purchaseQuery->where('customer_id', $customerId);
        }
        if ($packageId !== null) {
            $purchaseQuery->where('package_id', $packageId);
        }

        $matched = (clone $purchaseQuery)->count();
        if ($matched === 0) {
            $this->info('No purchases matched. Nothing to do.');
            return self::SUCCESS;
        }

        $this->info("Matched {$matched} purchase row(s). Processing…");

        $updated = 0;
        $skippedMissingTotal = 0;

        $purchaseQuery->orderBy('id')->chunkById(500, function ($purchases) use (
            $dryRun,
            $since,
            &$updated,
            &$skippedMissingTotal,
        ): void {
            foreach ($purchases as $purchase) {
                assert($purchase instanceof CustomerPackagePurchaseModel);

                $total = (int) ($purchase->total_sessions ?? 0);
                if ($total <= 0) {
                    $skippedMissingTotal++;
                    continue;
                }

                $usedQuery = BookingModel::query()
                    ->where('customer_id', $purchase->customer_id)
                    ->where('package_id', $purchase->package_id)
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->where(function ($q): void {
                        $q->whereNull('is_drop_in')->orWhere('is_drop_in', false);
                    });

                if ($since instanceof Carbon) {
                    $usedQuery->where('booked_at', '>=', $since);
                }

                $used = (int) $usedQuery->sum(DB::raw('GREATEST(1, COALESCE(party_size, 1))'));
                $newRemaining = max(0, $total - $used);
                $oldRemaining = (int) ($purchase->remaining_sessions ?? 0);

                if ($newRemaining === $oldRemaining) {
                    continue;
                }

                $this->line("purchase_id={$purchase->id} customer_id={$purchase->customer_id} package_id={$purchase->package_id} total={$total} used={$used} remaining {$oldRemaining}→{$newRemaining}");

                if (! $dryRun) {
                    $purchase->remaining_sessions = $newRemaining;
                    $purchase->save();
                }

                $updated++;
            }
        });

        $this->newLine();
        $this->info('Done.');
        $this->line("Updated purchases: {$updated}");
        if ($skippedMissingTotal > 0) {
            $this->warn("Skipped (total_sessions <= 0): {$skippedMissingTotal}");
        }

        return self::SUCCESS;
    }
}

