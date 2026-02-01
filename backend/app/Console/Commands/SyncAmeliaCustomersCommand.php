<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncAmeliaCustomersCommand extends Command
{
    protected $signature = 'users:sync-amelia
                            {--dry-run : Only report what would be done}
                            {--link-firebase : Also link Laravel customers (from Firestore) to Amelia by email/phone}';

    protected $description = 'Sync Amelia customers (WordPress) with Laravel customers; optionally link Firebase-migrated customers to Amelia';

    private bool $dryRun = false;
    private int $created = 0;
    private int $updated = 0;
    private int $linked = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $linkFirebase = (bool) $this->option('link-firebase');

        if ($this->dryRun) {
            $this->warn('Dry run: no changes will be written.');
        }

        $this->syncAmeliaToLaravel();

        if ($linkFirebase) {
            $this->linkFirebaseMigratedToAmelia();
        }

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Laravel customers created (from Amelia)', $this->created],
                ['Laravel customers updated (amelia_user_id / profile)', $this->updated],
                ['Firebase-migrated customers linked to Amelia', $this->linked],
                ['Skipped (already synced)', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * For each Amelia customer: find or create Laravel customer and set amelia_user_id.
     */
    private function syncAmeliaToLaravel(): void
    {
        $this->info('Syncing Amelia customers → Laravel...');

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error('WordPress/Amelia database not reachable. Check WP_DB_* in .env and that MySQL is running.');
            return;
        }

        $ameliaCustomers = AmeliaUserModel::on('wordpress')
            ->where('type', 'customer')
            ->get();

        foreach ($ameliaCustomers as $amelia) {
            $laravel = Customer::query()
                ->where('amelia_user_id', $amelia->id)
                ->orWhere(function ($q) use ($amelia) {
                    if (! empty($amelia->email)) {
                        $q->where('email', $amelia->email);
                    }
                    if (! empty($amelia->phone)) {
                        $q->orWhere('phone', $amelia->phone);
                    }
                })
                ->first();

            if ($laravel) {
                if ($laravel->amelia_user_id == $amelia->id) {
                    $this->skipped++;
                    continue;
                }
                if (! $this->dryRun) {
                    $laravel->amelia_user_id = $amelia->id;
                    $laravel->first_name = $laravel->first_name ?: ($amelia->firstName ?? '');
                    $laravel->last_name = $laravel->last_name ?: ($amelia->lastName ?? '');
                    $laravel->email = $laravel->email ?: ($amelia->email ?? null);
                    $laravel->phone = $laravel->phone ?: ($amelia->phone ?? null);
                    $laravel->save();
                }
                $this->updated++;
                continue;
            }

            if ($this->dryRun) {
                $this->created++;
                continue;
            }

            Customer::query()->create([
                'amelia_user_id' => $amelia->id,
                'first_name' => $amelia->firstName ?? 'Customer',
                'last_name' => $amelia->lastName ?? null,
                'email' => $amelia->email ?? null,
                'phone' => $amelia->phone ?? null,
                'source' => 'amelia',
            ]);
            $this->created++;
        }
    }

    /**
     * For each Laravel customer that has Firebase uid but no amelia_user_id: try to find Amelia customer by email/phone and set amelia_user_id.
     */
    private function linkFirebaseMigratedToAmelia(): void
    {
        $this->info('Linking Firebase-migrated customers to Amelia (by email/phone)...');

        $candidates = Customer::query()
            ->whereNull('amelia_user_id')
            ->where(function ($q) {
                $q->whereNotNull('uid')->orWhereNotNull('firebase_uid');
            })
            ->get();

        foreach ($candidates as $customer) {
            $amelia = null;
            if (! empty($customer->email)) {
                $amelia = AmeliaUserModel::on('wordpress')
                    ->where('type', 'customer')
                    ->where('email', $customer->email)
                    ->first();
            }
            if (! $amelia && ! empty($customer->phone)) {
                $amelia = AmeliaUserModel::on('wordpress')
                    ->where('type', 'customer')
                    ->where('phone', $customer->phone)
                    ->first();
            }

            if (! $amelia) {
                continue;
            }

            if (! $this->dryRun) {
                $customer->amelia_user_id = $amelia->id;
                $customer->save();
            }
            $this->linked++;
        }
    }
}
