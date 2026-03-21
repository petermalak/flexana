<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerDeviceTokenModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Models\Customer;
use App\Support\PhoneNumberNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Merges duplicate customers that share the same normalized phone (Firestore migration vs app signup).
 * Moves FKs to one canonical row and deletes the duplicate. Use --force to apply; default is dry-run.
 */
class MergeFirestoreDuplicateCustomersCommand extends Command
{
    protected $signature = 'customers:merge-firestore-duplicates
                            {--force : Apply merges (without this, only report what would happen)}
                            {--keep-customer-id= : Prefer this customer id as canonical when it appears in a duplicate group}';

    protected $description = 'Merge duplicate customers by normalized phone: move purchases/bookings/tokens to one row (fixes /auth/me missing packages)';

    public function handle(): int
    {
        $dryRun = ! (bool) $this->option('force');
        $preferId = $this->option('keep-customer-id');
        $preferId = $preferId !== null && $preferId !== '' ? (int) $preferId : null;

        if ($dryRun) {
            $this->warn('DRY RUN — no database changes. Re-run with --force to apply after backup.');
        }

        $customers = Customer::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->orderBy('id')
            ->get();

        /** @var array<string, \Illuminate\Support\Collection<int, Customer>> $byPhone */
        $byPhone = [];
        foreach ($customers as $c) {
            $norm = PhoneNumberNormalizer::normalize((string) $c->phone);
            if ($norm === '') {
                continue;
            }
            if (! isset($byPhone[$norm])) {
                $byPhone[$norm] = collect();
            }
            $byPhone[$norm]->push($c);
        }

        $groups = collect($byPhone)->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate phone groups found (normalized).');

            return self::SUCCESS;
        }

        $this->info('Found ' . $groups->count() . ' phone group(s) with more than one customer.');

        foreach ($groups as $phoneNorm => $group) {
            $canonical = $this->pickCanonical($group, $preferId);
            if ($canonical === null) {
                $this->error("Could not pick canonical for phone {$phoneNorm}");

                continue;
            }

            $duplicates = $group->filter(fn (Customer $c) => (int) $c->id !== (int) $canonical->id)->values();

            $this->newLine();
            $this->line("Phone <fg=cyan>{$phoneNorm}</> — canonical: <fg=green>{$canonical->id}</> ({$canonical->email})");
            foreach ($duplicates as $d) {
                $this->line("  merge away: <fg=yellow>{$d->id}</> uid=" . ($d->uid ?? 'null') . ' purchases=' . $this->purchaseCount($d->id));
            }

            if ($dryRun) {
                continue;
            }

            foreach ($duplicates as $duplicate) {
                $this->mergeDuplicateIntoCanonical($canonical, $duplicate);
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('End of dry run.');
        } else {
            $this->newLine();
            $this->info('Merge completed.');
        }

        return self::SUCCESS;
    }

    private function purchaseCount(int $customerId): int
    {
        return (int) CustomerPackagePurchaseModel::query()->where('customer_id', $customerId)->count();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Customer>  $group
     */
    private function pickCanonical($group, ?int $preferId): ?Customer
    {
        if ($preferId !== null) {
            $picked = $group->first(fn (Customer $c) => (int) $c->id === $preferId);
            if ($picked) {
                return $picked;
            }
        }

        // Prefer app accounts (password set). If several, keep **newest** id (current login is usually highest id).
        $withPassword = $group->filter(fn (Customer $c) => ! empty($c->password));
        if ($withPassword->isNotEmpty()) {
            return $withPassword->sortByDesc('id')->first();
        }

        // Prefer Firebase-linked row when none have password
        $withUid = $group->filter(fn (Customer $c) => ! empty($c->uid) || ! empty($c->firebase_uid));
        if ($withUid->count() === 1) {
            return $withUid->first();
        }

        // Prefer most package purchases, then highest id
        return $group->sort(function (Customer $a, Customer $b): int {
            $pa = $this->purchaseCount((int) $a->id);
            $pb = $this->purchaseCount((int) $b->id);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }

            return $b->id <=> $a->id;
        })->first();
    }

    private function mergeDuplicateIntoCanonical(Customer $canonical, Customer $duplicate): void
    {
        $canonical->refresh();

        $dupIdForLog = (int) $duplicate->id;

        DB::transaction(function () use ($canonical, $duplicate): void {
            $dupId = (int) $duplicate->id;
            $canId = (int) $canonical->id;

            // Capture before delete — and copy uid only after duplicate row is removed (customers.uid is unique).
            $dupUid = $duplicate->uid;
            $dupFirebase = $duplicate->firebase_uid;
            $dupEmail = $duplicate->email;

            CustomerPackagePurchaseModel::query()->where('customer_id', $dupId)->update(['customer_id' => $canId]);

            BookingModel::query()->where('customer_id', $dupId)->update(['customer_id' => $canId]);

            $tokens = CustomerDeviceTokenModel::query()->where('customer_id', $dupId)->get();
            foreach ($tokens as $t) {
                $conflict = CustomerDeviceTokenModel::query()
                    ->where('customer_id', $canId)
                    ->where('device_id', $t->device_id)
                    ->exists();
                if ($conflict) {
                    $t->delete();
                } else {
                    $t->customer_id = $canId;
                    $t->save();
                }
            }

            $tokenableType = Customer::class;
            DB::table('personal_access_tokens')
                ->where('tokenable_type', $tokenableType)
                ->where('tokenable_id', $dupId)
                ->update(['tokenable_id' => $canId]);

            if (Schema::hasColumn('phone_verification_codes', 'customer_id')) {
                DB::table('phone_verification_codes')
                    ->where('customer_id', $dupId)
                    ->update(['customer_id' => $canId]);
            }

            // Remove duplicate row first so Firebase uid can be assigned to canonical without unique violation.
            Customer::query()->whereKey($dupId)->delete();

            $canonical->refresh();

            if (empty($canonical->uid) && ! empty($dupUid)) {
                $canonical->uid = $dupUid;
            }
            if (empty($canonical->firebase_uid) && ! empty($dupFirebase)) {
                $canonical->firebase_uid = $dupFirebase;
            }
            if (empty($canonical->email) && ! empty($dupEmail)) {
                $canonical->email = $dupEmail;
            }
            $canonical->phone = PhoneNumberNormalizer::normalizeNullable($canonical->phone) ?? $canonical->phone;
            $canonical->save();
        });

        $this->line("  Merged customer {$dupIdForLog} into {$canonical->id}.");
    }
}
