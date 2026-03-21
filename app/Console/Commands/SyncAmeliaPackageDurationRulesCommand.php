<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\PackageModel;
use Illuminate\Console\Command;

/**
 * Sets package_duration (months) and package_duration_days from known Amelia/Firestore package IDs
 * so /auth/me and booking flows can compute expiresAt from purchase_date.
 */
class SyncAmeliaPackageDurationRulesCommand extends Command
{
    protected $signature = 'packages:sync-amelia-duration-rules
                            {--dry-run : Show changes without saving}';

    protected $description = 'Apply Flexana duration rules by amelia_package_id (Yoga 3mo, Reformer 12mo, Unlimited 30d/3mo)';

    /**
     * Amelia package id => [ months => int|null, days => int|null ]
     * Only one of months/days should be set per row.
     */
    private const RULES_BY_AMELIA_ID = [
        // Yoga — 3 months
        44 => ['months' => 3, 'days' => null],
        45 => ['months' => 3, 'days' => null],
        46 => ['months' => 3, 'days' => null],
        // Reformer — 12 months
        40 => ['months' => 12, 'days' => null],
        41 => ['months' => 12, 'days' => null],
        // Unlimited
        47 => ['months' => null, 'days' => 30],
        48 => ['months' => 3, 'days' => null],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        $updated = 0;
        foreach (self::RULES_BY_AMELIA_ID as $ameliaId => $rule) {
            $packages = PackageModel::query()->where('amelia_package_id', (int) $ameliaId)->get();
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

        return self::SUCCESS;
    }
}
