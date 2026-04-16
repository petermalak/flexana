<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Support\PackagePurchaseExpiry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Fix bad synced "unlimited" package purchases that were imported with
 * total_sessions=1 and remaining_sessions=0 (or other non-positive values).
 *
 * Unlimited packages are identified by Amelia package IDs configured in duration rules:
 * - 47 => 30 days
 * - 48 => 3 months
 *
 * This command:
 * - Sets a large sentinel (default 9999) for total_sessions/remaining_sessions on valid (not expired) purchases.
 * - Never changes purchase status. Expired rows are only reported.
 */
class FixUnlimitedPackagePurchasesCommand extends Command
{
    protected $signature = 'packages:fix-unlimited-purchases
                            {--dry-run : Show what would change without writing}
                            {--sentinel=9999 : Sentinel value to use for total/remaining sessions}
                            {--unlimited-30d-id=47 : Amelia package id for 30-day unlimited}
                            {--unlimited-3mo-id=48 : Amelia package id for 3-month unlimited}
                            {--include-null-ids : Also fix rows where both package_id and amelia_package_id are NULL (requires --null-id-duration)}
                            {--null-id-duration= : When using --include-null-ids, assume these rows expire by: 30d or 3mo}';

    protected $description = 'Fix synced unlimited package purchases (Amelia 47/48) that have total=1/remaining=0 by setting total/remaining sessions, while respecting 30d/3mo expiry (no status changes).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sentinel = max(1, (int) $this->option('sentinel'));
        $includeNullIds = (bool) $this->option('include-null-ids');
        $nullIdDuration = strtolower(trim((string) $this->option('null-id-duration')));

        $id30d = (int) $this->option('unlimited-30d-id');
        $id3mo = (int) $this->option('unlimited-3mo-id');
        $unlimitedAmeliaIds = array_values(array_unique(array_filter([$id30d, $id3mo], fn ($v) => (int) $v > 0)));

        $bizTz = (string) config('app.business_timezone', config('app.timezone'));
        $today = Carbon::now($bizTz)->startOfDay();

        if ($dryRun) {
            $this->warn('Dry run — no changes will be written.');
        }

        if ($includeNullIds) {
            if (! in_array($nullIdDuration, ['30d', '3mo'], true)) {
                $this->error('When using --include-null-ids you must also provide --null-id-duration=30d or --null-id-duration=3mo');
                return self::FAILURE;
            }
            $this->warn("Including NULL-id rows (package_id NULL + amelia_package_id NULL) assuming duration={$nullIdDuration}.");
        }

        $this->info('Scanning customer_package_purchases for bad unlimited rows…');

        $query = CustomerPackagePurchaseModel::query()
            ->with('package')
            // Some synced rows have amelia_package_id missing on the purchase row,
            // but the related packages row has it. Match either source.
            //
            // As a fallback (when neither purchase nor package has an Amelia id),
            // also match packages that look like "unlimited" products (title/description)
            // with typical unlimited durations (30 days or 3 months).
            ->where(function ($q) use ($unlimitedAmeliaIds, $includeNullIds): void {
                // Primary match: by Amelia package id (on purchase row or package row).
                $q->whereIn('amelia_package_id', $unlimitedAmeliaIds)
                    ->orWhereHas('package', function ($qp) use ($unlimitedAmeliaIds): void {
                        $qp->whereIn('amelia_package_id', $unlimitedAmeliaIds);
                    })
                    ->orWhereHas('package', function ($qp): void {
                        $qp->where(function ($qText): void {
                            $qText->whereRaw('LOWER(COALESCE(title, "")) LIKE ?', ['%unlimited%'])
                                ->orWhereRaw('LOWER(COALESCE(description, "")) LIKE ?', ['%unlimited%']);
                        })
                        ->where(function ($qDur): void {
                            $qDur->where('package_duration_days', 30)
                                ->orWhere('package_duration', 3);
                        });
                    });

                if ($includeNullIds) {
                    $q->orWhere(function ($qNull): void {
                        $qNull->whereNull('package_id')->whereNull('amelia_package_id');
                    });
                }
            })
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->where('remaining_sessions', '<=', 0)
                    ->orWhere('total_sessions', '<=', 1);
            })
            ->orderBy('id');

        $totalMatched = (clone $query)->count();
        if ($totalMatched === 0) {
            $this->info('No matching purchases found. Nothing to fix.');
            return self::SUCCESS;
        }

        $this->info("Matched {$totalMatched} purchase row(s). Processing…");

        $fixed = 0;
        $expired = 0;
        $skippedNoPurchaseDate = 0;

        $query->chunkById(500, function ($purchases) use (
            $dryRun,
            $sentinel,
            $id30d,
            $id3mo,
            $includeNullIds,
            $nullIdDuration,
            $bizTz,
            $today,
            &$fixed,
            &$expired,
            &$skippedNoPurchaseDate,
        ): void {
            foreach ($purchases as $purchase) {
                assert($purchase instanceof CustomerPackagePurchaseModel);

                if (! $purchase->purchase_date) {
                    $skippedNoPurchaseDate++;
                    $this->line("skip id={$purchase->id} customer_id={$purchase->customer_id} (missing purchase_date)");
                    continue;
                }

                $effectiveAmeliaId = (int) ($purchase->amelia_package_id ?? 0);
                if ($effectiveAmeliaId <= 0) {
                    $effectiveAmeliaId = (int) ($purchase->package?->amelia_package_id ?? 0);
                }

                // Prefer explicit unlimited durations for the configured Amelia ids, even if
                // the shared duration rules map isn't up to date for this environment.
                $expiresAt = null;
                if ($effectiveAmeliaId > 0 && $effectiveAmeliaId === $id30d) {
                    $expiresAt = $purchase->purchase_date->copy()->addDays(30);
                } elseif ($effectiveAmeliaId > 0 && $effectiveAmeliaId === $id3mo) {
                    $expiresAt = $purchase->purchase_date->copy()->addMonths(3);
                } elseif (
                    $includeNullIds
                    && $effectiveAmeliaId <= 0
                    && $purchase->package_id === null
                    && $purchase->amelia_package_id === null
                ) {
                    $expiresAt = $nullIdDuration === '30d'
                        ? $purchase->purchase_date->copy()->addDays(30)
                        : $purchase->purchase_date->copy()->addMonths(3);
                } else {
                    $expiresAt = PackagePurchaseExpiry::expiresAt(
                        $purchase->package,
                        $purchase->purchase_date,
                        $effectiveAmeliaId > 0 ? $effectiveAmeliaId : null,
                        (bool) $purchase->expires_by_months_only,
                    );
                }

                $isExpired = $expiresAt !== null
                    && $expiresAt->copy()->timezone($bizTz)->startOfDay()->lt($today);

                if ($isExpired) {
                    $this->line("expired id={$purchase->id} customer_id={$purchase->customer_id} (expired by {$expiresAt})");
                    $expired++;
                    continue;
                }

                $beforeTotal = (int) ($purchase->total_sessions ?? 0);
                $beforeRemaining = (int) ($purchase->remaining_sessions ?? 0);

                $this->line("fix id={$purchase->id} customer_id={$purchase->customer_id} amelia_package_id={$effectiveAmeliaId} total {$beforeTotal}→{$sentinel} remaining {$beforeRemaining}→{$sentinel}");

                if (! $dryRun) {
                    $purchase->total_sessions = $sentinel;
                    $purchase->remaining_sessions = $sentinel;
                    $purchase->save();
                }

                $fixed++;
            }
        });

        $this->newLine();
        $this->info('Done.');
        $this->line("Matched: {$totalMatched}");
        $this->line("Fixed (set sentinel): {$fixed}");
        $this->line("Expired encountered: {$expired} (reported only; no status changes)");
        if ($skippedNoPurchaseDate > 0) {
            $this->warn("Skipped (missing purchase_date): {$skippedNoPurchaseDate}");
        }

        return self::SUCCESS;
    }
}

