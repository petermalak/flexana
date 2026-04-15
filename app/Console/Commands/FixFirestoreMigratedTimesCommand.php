<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fix timestamps imported from Firestore JSON exports.
 *
 * Historical issue:
 * - Firestore exports store timestamps as UTC (ISO-8601 "Z")
 * - Import stored those instants as naive MySQL timestamps (interpreted later in app timezone),
 *   resulting in wall-clock times being shifted (commonly -2 hours for Africa/Cairo).
 *
 * This command updates migrated rows in-place without deleting anything.
 * It is designed to be safely re-runnable by marking fixed payments in meta.tz_fixed.
 */
class FixFirestoreMigratedTimesCommand extends Command
{
    protected $signature = 'firestore:fix-migrated-times
                            {--force : Apply updates (without this, only report what would happen)}';

    protected $description = 'Fix Firestore-migrated booking/payment timestamps by reinterpreting stored datetimes as UTC and converting to app timezone';

    public function handle(): int
    {
        $dryRun = ! (bool) $this->option('force');
        $toTz = (string) config('app.timezone', 'UTC');
        $fromTz = 'UTC';

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes. Re-run with --force to apply.');
        }

        $updatedBookings = 0;
        $updatedPayments = 0;
        $skipped = 0;

        PaymentModel::query()
            ->where('provider', 'firestore_migrated')
            ->orderBy('id')
            ->chunkById(200, function ($payments) use ($dryRun, $fromTz, $toTz, &$updatedBookings, &$updatedPayments, &$skipped): void {
                /** @var PaymentModel $payment */
                foreach ($payments as $payment) {
                    $meta = is_array($payment->meta) ? $payment->meta : [];
                    if (($meta['tz_fixed'] ?? false) === true) {
                        $skipped++;
                        continue;
                    }

                    DB::transaction(function () use ($payment, $dryRun, $fromTz, $toTz, &$updatedBookings, &$updatedPayments): void {
                        $payment->refresh();

                        $meta = is_array($payment->meta) ? $payment->meta : [];
                        if (($meta['tz_fixed'] ?? false) === true) {
                            return;
                        }

                        $booking = $payment->booking()->lockForUpdate()->first();

                        $bookingChanged = false;
                        $paymentChanged = false;

                        if ($booking && $booking->booked_at) {
                            $fixedBookedAt = $this->reinterpretWallClockAsTimezone($booking->booked_at, $fromTz, $toTz);
                            if ($fixedBookedAt !== null && ! $fixedBookedAt->equalTo($booking->booked_at)) {
                                $bookingChanged = true;
                                if (! $dryRun) {
                                    $booking->booked_at = $fixedBookedAt;
                                }
                            }
                        }

                        if ($payment->paid_at) {
                            $fixedPaidAt = $this->reinterpretWallClockAsTimezone($payment->paid_at, $fromTz, $toTz);
                            if ($fixedPaidAt !== null && ! $fixedPaidAt->equalTo($payment->paid_at)) {
                                $paymentChanged = true;
                                if (! $dryRun) {
                                    $payment->paid_at = $fixedPaidAt;
                                }
                            }
                        }

                        if ($bookingChanged && ! $dryRun && $booking) {
                            $booking->save();
                            $updatedBookings++;
                        } elseif ($bookingChanged) {
                            $updatedBookings++;
                        }

                        if ($paymentChanged && ! $dryRun) {
                            $payment->save();
                            $updatedPayments++;
                        } elseif ($paymentChanged) {
                            $updatedPayments++;
                        }

                        // Mark *all* firestore_migrated payments for this booking as fixed so re-runs are safe.
                        // Do this even if no timestamp changed (it might already be correct).
                        $marker = [
                            'tz_fixed' => true,
                            'tz_fixed_at' => now()->toIso8601String(),
                            'tz_from' => $fromTz,
                            'tz_to' => $toTz,
                        ];

                        if (! $dryRun) {
                            /** @var \Illuminate\Database\Eloquent\Collection<int, PaymentModel> $paymentsForBooking */
                            $paymentsForBooking = PaymentModel::query()
                                ->where('provider', 'firestore_migrated')
                                ->where('booking_id', $payment->booking_id)
                                ->lockForUpdate()
                                ->get();

                            /** @var PaymentModel $p */
                            foreach ($paymentsForBooking as $p) {
                                $m = is_array($p->meta) ? $p->meta : [];
                                $p->meta = array_merge($m, $marker);
                                $p->save();
                            }
                        }
                    });
                }
            });

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Bookings booked_at fixed', $updatedBookings],
                ['Payments paid_at fixed', $updatedPayments],
                ['Skipped (already marked tz_fixed)', $skipped],
            ]
        );

        $this->newLine();
        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --force to apply.');
        } else {
            $this->info('Timezone fix applied.');
        }

        return self::SUCCESS;
    }

    /**
     * Take a stored datetime (currently interpreted in app TZ) and treat its wall-clock
     * as if it were in $assumedTz, then convert to $targetTz.
     *
     * Example:
     * - Stored: "2026-01-10 14:42:20" (but this was originally UTC)
     * - assumedTz=UTC, targetTz=Africa/Cairo => becomes "2026-01-10 16:42:20"
     */
    private function reinterpretWallClockAsTimezone(Carbon $value, string $assumedTz, string $targetTz): ?Carbon
    {
        $wall = $value->format('Y-m-d H:i:s');
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $wall, $assumedTz)->timezone($targetTz);
        } catch (\Throwable) {
            return null;
        }
    }
}

