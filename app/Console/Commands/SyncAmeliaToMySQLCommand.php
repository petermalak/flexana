<?php

namespace App\Console\Commands;

use App\Application\Amelia\Services\AutoSyncAmeliaService;
use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPaymentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaProviderServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive command to sync/clone ALL data from WordPress/Amelia database to MySQL database.
 * This ensures all WordPress data is available in MySQL before WordPress database is deleted.
 */
class SyncAmeliaToMySQLCommand extends Command
{
    protected $signature = 'amelia:sync-to-mysql
                            {--dry-run : Only report what would be done}
                            {--skip-duplicates : Skip records that already exist}
                            {--only=* : Limit to entities: customers,staff,services,packages,service-staff,package-service,appointments,bookings,payments}';

    protected $description = 'Sync/clone ALL Amelia (WordPress) data to MySQL database';

    private bool $dryRun = false;
    private bool $skipDuplicates = false;
    private array $only = [];
    private array $counts = [
        'customers' => 0,
        'staff' => 0,
        'services' => 0,
        'packages' => 0,
        'service_staff' => 0,
        'package_service' => 0,
        'appointments' => 0,
        'bookings' => 0,
        'payments' => 0,
        'skipped' => 0,
    ];

    public function __construct(
        private readonly AutoSyncAmeliaService $autoSync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->skipDuplicates = (bool) $this->option('skip-duplicates');
        $this->only = $this->option('only') ?: [];

        if (! config('database.connections.wordpress')) {
            $this->warn('WordPress database connection is disabled. All data is now in MySQL.');
            $this->info('No sync needed - use your MySQL database (flexana) as the single source of truth.');
            return self::SUCCESS;
        }

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error('WordPress/Amelia database not reachable. Check WP_DB_* in .env.');
            return self::FAILURE;
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('MySQL database not reachable. Check DB_* in .env.');
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run: no changes will be written.');
        }

        $this->info('Starting sync from WordPress to MySQL...');
        $this->newLine();

        $this->syncCustomers();
        $this->syncStaff();
        $this->syncServices();
        $this->syncPackages();
        $this->syncServiceStaff();
        $this->syncPackageService();
        $this->syncAppointments();
        $this->syncBookings();
        $this->syncPayments();

        $this->newLine();
        $this->info('Sync Summary:');
        $this->table(
            ['Entity', 'Synced/Updated'],
            collect($this->counts)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()
        );

        $this->newLine();
        $this->info('✅ All WordPress data has been cloned to MySQL database!');
        $this->info('You can now safely delete the WordPress database when ready.');

        return self::SUCCESS;
    }

    private function shouldRun(string $key): bool
    {
        if (empty($this->only)) {
            return true;
        }
        $map = [
            'customers' => 'customers',
            'staff' => 'staff',
            'services' => 'services',
            'packages' => 'packages',
            'service-staff' => 'service_staff',
            'package-service' => 'package_service',
            'appointments' => 'appointments',
            'bookings' => 'bookings',
            'payments' => 'payments',
        ];
        return in_array($map[$key] ?? $key, $this->only, true)
            || in_array(str_replace('_', '-', $key), $this->only, true);
    }

    private function syncCustomers(): void
    {
        if (! $this->shouldRun('customers')) {
            return;
        }
        $this->info('Syncing customers...');
        $ameliaCustomers = AmeliaUserModel::on('wordpress')
            ->where('type', 'customer')
            ->get();

        /** @var AmeliaUserModel $a */
        foreach ($ameliaCustomers as $a) {
            $existing = \App\Models\Customer::query()
                ->where('amelia_user_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['customers']++;
                continue;
            }

            $this->autoSync->syncCustomer($a);
            $this->counts['customers']++;
        }
        $this->line("  → Synced {$this->counts['customers']} customers");
    }

    private function syncStaff(): void
    {
        if (! $this->shouldRun('staff')) {
            return;
        }
        $this->info('Syncing staff...');
        $ameliaUsers = AmeliaUserModel::on('wordpress')
            ->whereIn('type', ['provider', 'manager', 'admin'])
            ->get();

        foreach ($ameliaUsers as $a) {
            $existing = \App\Infrastructure\Persistence\Eloquent\StaffModel::query()
                ->where('amelia_user_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['staff']++;
                continue;
            }

            $this->autoSync->syncStaff($a);
            $this->counts['staff']++;
        }
        $this->line("  → Synced {$this->counts['staff']} staff members");
    }

    private function syncServices(): void
    {
        if (! $this->shouldRun('services')) {
            return;
        }
        $this->info('Syncing services...');
        $rows = AmeliaServiceModel::on('wordpress')->get();

        /** @var AmeliaServiceModel $a */
        foreach ($rows as $a) {
            $existing = \App\Infrastructure\Persistence\Eloquent\ServiceModel::query()
                ->where('amelia_service_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['services']++;
                continue;
            }

            $this->autoSync->syncService($a);
            $this->counts['services']++;
        }
        $this->line("  → Synced {$this->counts['services']} services");
    }

    private function syncPackages(): void
    {
        if (! $this->shouldRun('packages')) {
            return;
        }
        $this->info('Syncing packages...');
        $rows = AmeliaPackageModel::on('wordpress')->get();

        foreach ($rows as $a) {
            $existing = \App\Infrastructure\Persistence\Eloquent\PackageModel::query()
                ->where('amelia_package_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['packages']++;
                continue;
            }

            $this->autoSync->syncPackage($a);
            $this->counts['packages']++;
        }
        $this->line("  → Synced {$this->counts['packages']} packages");
    }

    private function syncServiceStaff(): void
    {
        if (! $this->shouldRun('service_staff')) {
            return;
        }
        $this->info('Syncing service-staff relationships...');
        $rows = AmeliaProviderServiceModel::on('wordpress')->get();

        foreach ($rows as $a) {
            if ($this->dryRun) {
                $this->counts['service_staff']++;
                continue;
            }

            $this->autoSync->syncServiceStaff($a->userId, $a->serviceId);
            $this->counts['service_staff']++;
        }
        $this->line("  → Synced {$this->counts['service_staff']} service-staff relationships");
    }

    private function syncPackageService(): void
    {
        if (! $this->shouldRun('package_service')) {
            return;
        }
        $this->info('Syncing package-service relationships...');
        $rows = AmeliaPackageServiceModel::on('wordpress')->get();

        foreach ($rows as $a) {
            $quantity = (int) ($a->quantity ?? 1);
            if ($this->dryRun) {
                $this->counts['package_service']++;
                continue;
            }

            $this->autoSync->syncPackageService($a->packageId, $a->serviceId, $quantity);
            $this->counts['package_service']++;
        }
        $this->line("  → Synced {$this->counts['package_service']} package-service relationships");
    }

    private function syncAppointments(): void
    {
        if (! $this->shouldRun('appointments')) {
            return;
        }
        $this->info('Syncing appointments...');
        $rows = AmeliaAppointmentModel::on('wordpress')->get();

        /** @var AmeliaAppointmentModel $a */
        foreach ($rows as $a) {
            $existing = \App\Infrastructure\Persistence\Eloquent\AppointmentModel::query()
                ->where('amelia_appointment_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['appointments']++;
                continue;
            }

            $this->autoSync->syncAppointment($a);
            $this->counts['appointments']++;
        }
        $this->line("  → Synced {$this->counts['appointments']} appointments");
    }

    private function syncBookings(): void
    {
        if (! $this->shouldRun('bookings')) {
            return;
        }
        $this->info('Syncing bookings...');
        $rows = AmeliaCustomerBookingModel::on('wordpress')->get();

        /** @var AmeliaCustomerBookingModel $a */
        foreach ($rows as $a) {
            $existing = \App\Infrastructure\Persistence\Eloquent\BookingModel::query()
                ->where('amelia_customer_booking_id', $a->id)
                ->exists();

            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $this->counts['bookings']++;
                continue;
            }

            $this->autoSync->syncBooking($a);
            $this->counts['bookings']++;
        }
        $this->line("  → Synced {$this->counts['bookings']} bookings");
    }

    private function syncPayments(): void
    {
        if (! $this->shouldRun('payments')) {
            return;
        }
        $this->info('Syncing payments...');
        $rows = AmeliaPaymentModel::on('wordpress')->get();

        /** @var AmeliaPaymentModel $a */
        foreach ($rows as $a) {
            if ($this->dryRun) {
                $this->counts['payments']++;
                continue;
            }

            $this->autoSync->syncPayment($a);
            $this->counts['payments']++;
        }
        $this->line("  → Synced {$this->counts['payments']} payments");
    }
}
