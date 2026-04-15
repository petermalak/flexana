<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mark duplicate Amelia appointments created with timezone-shifted times.
 *
 * Symptom (admin/appointments):
 * - Same instructor + same service appears twice
 * - One is correct, the other is earlier by ~3 hours (Egypt DST) or ~2 hours (winter)
 *
 * We do NOT delete anything:
 * - Re-point bookings from the duplicate appointment to the canonical appointment
 * - Mark the duplicate appointment as canceled, and annotate internal_notes
 *
 * This command is designed to be safely re-runnable: it skips appointments already marked.
 */
class MarkTimezoneDuplicateAppointmentsCommand extends Command
{
    protected $signature = 'appointments:mark-timezone-duplicates
                            {--hours=3 : Offset in hours to treat as a timezone duplicate (commonly 3 in DST, 2 in winter)}
                            {--force : Apply changes (without this, only report what would happen)}';

    protected $description = 'Detect timezone-shifted duplicate appointments and cancel the shifted copy (without deleting), moving bookings to the canonical row';

    public function handle(): int
    {
        $dryRun = ! (bool) $this->option('force');
        $offsetHours = (int) ($this->option('hours') ?? 3);
        if ($offsetHours === 0) {
            $offsetHours = 3;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes. Re-run with --force to apply.');
        }

        $pairs = $this->findDuplicatePairs($offsetHours);
        if ($pairs->isEmpty()) {
            $this->info('No timezone-duplicate appointment pairs found.');
            return self::SUCCESS;
        }

        $this->info('Found ' . $pairs->count() . ' duplicate pair(s).');

        $updatedBookings = 0;
        $canceledAppointments = 0;
        $skipped = 0;

        foreach ($pairs as [$canonicalId, $duplicateId]) {
            /** @var AppointmentModel|null $canonical */
            $canonical = AppointmentModel::query()->whereKey($canonicalId)->first();
            /** @var AppointmentModel|null $duplicate */
            $duplicate = AppointmentModel::query()->whereKey($duplicateId)->first();

            if (! $canonical || ! $duplicate) {
                $skipped++;
                continue;
            }

            if ($this->isAlreadyMarked($duplicate)) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($canonical, $duplicate, $dryRun, &$updatedBookings, &$canceledAppointments): void {
                /** @var AppointmentModel|null $dupLocked */
                $dupLocked = AppointmentModel::query()->whereKey($duplicate->id)->lockForUpdate()->first();
                /** @var AppointmentModel|null $canLocked */
                $canLocked = AppointmentModel::query()->whereKey($canonical->id)->lockForUpdate()->first();

                if (! $dupLocked || ! $canLocked) {
                    return;
                }

                if ($this->isAlreadyMarked($dupLocked)) {
                    return;
                }

                // Move bookings over first (so the duplicate appointment can be safely canceled).
                $moved = BookingModel::query()
                    ->where('appointment_id', $dupLocked->id)
                    ->update(['appointment_id' => $canLocked->id]);
                $updatedBookings += (int) $moved;

                $note = $this->markNote($canLocked, $dupLocked);

                if (! $dryRun) {
                    $dupLocked->status = 'canceled';
                    $existingNotes = (string) ($dupLocked->internal_notes ?? '');
                    $dupLocked->internal_notes = trim($existingNotes . "\n" . $note);
                    $dupLocked->save();
                }

                $canceledAppointments++;
            });
        }

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Duplicate pairs detected', $pairs->count()],
                ['Bookings re-pointed to canonical appointment', $updatedBookings],
                ['Appointments marked canceled (duplicate copy)', $canceledAppointments],
                ['Skipped (already marked / missing)', $skipped],
            ]
        );

        $this->newLine();
        if ($dryRun) {
            $this->warn('Dry run complete. Re-run with --force to apply.');
        } else {
            $this->info('Duplicate appointments marked.');
        }

        return self::SUCCESS;
    }

    private function isAlreadyMarked(AppointmentModel $appointment): bool
    {
        $notes = (string) ($appointment->internal_notes ?? '');
        return str_contains($notes, '[tz-duplicate]');
    }

    private function markNote(AppointmentModel $canonical, AppointmentModel $duplicate): string
    {
        return sprintf(
            '[tz-duplicate] canonical_appointment_id=%d canonical_start=%s duplicate_start=%s',
            (int) $canonical->id,
            $canonical->booking_start instanceof Carbon ? $canonical->booking_start->format('Y-m-d H:i:s') : (string) $canonical->booking_start,
            $duplicate->booking_start instanceof Carbon ? $duplicate->booking_start->format('Y-m-d H:i:s') : (string) $duplicate->booking_start,
        );
    }

    /**
     * Find pairs of appointments which are identical except for start/end shifted by N hours.
     *
     * We return pairs [canonical_id, duplicate_id] where canonical is the one with the later start time.
     */
    private function findDuplicatePairs(int $offsetHours)
    {
        // Use a small SQL trick:
        // Find A and B where:
        // - same provider_id + service_id
        // - B.booking_start = A.booking_start + INTERVAL offsetHours HOUR
        // - B.booking_end   = A.booking_end   + INTERVAL offsetHours HOUR
        // - neither already canceled for tz-duplicate
        //
        // canonical = B (later), duplicate = A (earlier)
        $pairs = DB::table('appointments as a')
            ->join('appointments as b', function ($join) use ($offsetHours) {
                $join->on('b.provider_id', '=', 'a.provider_id')
                    ->on('b.service_id', '=', 'a.service_id')
                    ->whereRaw("b.booking_start = DATE_ADD(a.booking_start, INTERVAL {$offsetHours} HOUR)")
                    ->whereRaw("b.booking_end = DATE_ADD(a.booking_end, INTERVAL {$offsetHours} HOUR)");
            })
            ->where('a.status', '!=', 'canceled')
            ->where('b.status', '!=', 'canceled')
            ->where(function ($q) {
                $q->whereNull('a.internal_notes')->orWhere('a.internal_notes', 'not like', '%[tz-duplicate]%');
            })
            ->where(function ($q) {
                $q->whereNull('b.internal_notes')->orWhere('b.internal_notes', 'not like', '%[tz-duplicate]%');
            })
            ->select(['b.id as canonical_id', 'a.id as duplicate_id'])
            ->get()
            ->map(fn ($row) => [(int) $row->canonical_id, (int) $row->duplicate_id]);

        return $pairs;
    }
}

