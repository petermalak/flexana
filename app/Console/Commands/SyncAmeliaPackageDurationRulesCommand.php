<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Support\AmeliaPackageDurationRules;
use Illuminate\Console\Command;

/**
 * Sets package_duration (months) and package_duration_days from known Amelia/Firestore package IDs
 * so /auth/me and booking flows can compute expiresAt from purchase_date.
 *
 * Safe to run multiple times. Re-run after an older build applied inverted Yoga/Reformer months:
 * it overwrites those columns with the current rules from AmeliaPackageDurationRules.
 */
class SyncAmeliaPackageDurationRulesCommand extends Command
{
    protected $signature = 'packages:sync-amelia-duration-rules
                            {--dry-run : Show changes without saving}
                            {--skip-backfill : Do not backfill customer_package_purchases.amelia_package_id from packages}';

    protected $description = 'Apply Flexana duration rules on packages (Yoga 12mo, Reformer 3mo, Unlimited 30d/3mo). Re-run to fix a bad prior sync; optionally backfills purchase amelia ids.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $this->info('Updating packages.package_duration / package_duration_days from Amelia id rules…');

        $updated = 0;
        foreach (AmeliaPackageDurationRules::BY_AMELIA_ID as $ameliaId => $rule) {
            $packages = PackageModel::query()->where('amelia_package_id', (int) $ameliaId)->get();
            /** @var PackageModel $package */
            foreach ($packages as $package) {
                $payload = [
                    'package_duration' => $rule['months'],
                    'package_duration_days' => $rule['days'],
                ];
                $this->line("amelia_package_id={$ameliaId} Laravel id={$package->id} {$package->title}: " . json_encode($payload));

                if (! $dryRun) {
                    $package->update($payload);
                }
                $updated++;
            }
        }

        $this->newLine();
        if ($updated === 0) {
            $this->warn('No packages matched these amelia_package_id values. Check packages.amelia_package_id in the database.');
        } else {
            $this->info($dryRun ? "Would update {$updated} package row(s)." : "Updated {$updated} package row(s).");
        }

        if (! $this->option('skip-backfill')) {
            $this->newLine();
            $backfilled = $this->backfillPurchaseAmeliaPackageIds($dryRun);
            $this->info($dryRun
                ? "Would backfill amelia_package_id on {$backfilled} customer_package_purchase row(s) where package_id is set."
                : "Backfilled amelia_package_id on {$backfilled} customer_package_purchase row(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Purchases linked to a package row but missing amelia_package_id cannot resolve duration by id alone.
     */
    private function backfillPurchaseAmeliaPackageIds(bool $dryRun): int
    {
        $count = 0;
        foreach (CustomerPackagePurchaseModel::query()
            ->whereNull('amelia_package_id')
            ->whereNotNull('package_id')
            ->cursor() as $purchase) {
            assert($purchase instanceof CustomerPackagePurchaseModel);
            $amelia = PackageModel::query()->whereKey($purchase->package_id)->value('amelia_package_id');
            if ($amelia === null || (int) $amelia === 0) {
                continue;
            }
            $amelia = (int) $amelia;
            $this->line("customer_package_purchase id={$purchase->id} → amelia_package_id={$amelia}");
            if (! $dryRun) {
                $purchase->update(['amelia_package_id' => $amelia]);
            }
            $count++;
        }

        return $count;
    }
}
