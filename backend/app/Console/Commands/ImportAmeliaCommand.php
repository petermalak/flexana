<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaProviderServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportAmeliaCommand extends Command
{
    protected $signature = 'amelia:import
                            {--dry-run : Only report what would be done}
                            {--skip-duplicates : Skip records that already exist (e.g. by amelia_*_id)}
                            {--only=* : Limit to entities: staff,services,packages,service-staff,package-service,appointments,bookings}';

    protected $description = 'Import Amelia (WordPress) data into Laravel DB (staff, services, packages, appointments, bookings)';

    private bool $dryRun = false;
    private bool $skipDuplicates = false;
    private array $only = [];
    private array $counts = [
        'staff' => 0,
        'services' => 0,
        'packages' => 0,
        'service_staff' => 0,
        'package_service' => 0,
        'appointments' => 0,
        'bookings' => 0,
        'skipped' => 0,
    ];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->skipDuplicates = (bool) $this->option('skip-duplicates');
        $this->only = $this->option('only') ?: [];

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error('WordPress/Amelia database not reachable. Check WP_DB_* in .env.');
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run: no changes will be written.');
        }

        $this->importStaff();
        $this->importServices();
        $this->importPackages();
        $this->importServiceStaff();
        $this->importPackageService();
        $this->importAppointments();
        $this->importBookings();

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Entity', 'Created/Updated'],
            collect($this->counts)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()
        );

        return self::SUCCESS;
    }

    private function shouldRun(string $key): bool
    {
        if (empty($this->only)) {
            return true;
        }
        $map = [
            'staff' => 'staff',
            'services' => 'services',
            'packages' => 'packages',
            'service-staff' => 'service_staff',
            'package-service' => 'package_service',
            'appointments' => 'appointments',
            'bookings' => 'bookings',
        ];
        return in_array($map[$key] ?? $key, $this->only, true)
            || in_array(str_replace('_', '-', $key), $this->only, true);
    }

    private function importStaff(): void
    {
        if (! $this->shouldRun('staff')) {
            return;
        }
        $this->info('Importing staff (Amelia users: provider/manager/admin)...');
        $ameliaUsers = AmeliaUserModel::on('wordpress')
            ->whereIn('type', ['provider', 'manager', 'admin'])
            ->get();

        foreach ($ameliaUsers as $a) {
            $existing = StaffModel::query()->where('amelia_user_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'name' => trim(($a->firstName ?? '') . ' ' . ($a->lastName ?? '')),
                        'email' => $a->email,
                        'phone' => $a->phone,
                        'is_active' => ($a->status ?? 'visible') === 'visible',
                    ]);
                }
                $this->counts['staff']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['staff']++;
                continue;
            }
            StaffModel::query()->create([
                'amelia_user_id' => $a->id,
                'name' => trim(($a->firstName ?? '') . ' ' . ($a->lastName ?? '')) ?: 'Staff ' . $a->id,
                'email' => $a->email,
                'phone' => $a->phone,
                'is_active' => ($a->status ?? 'visible') === 'visible',
            ]);
            $this->counts['staff']++;
        }
    }

    private function importServices(): void
    {
        if (! $this->shouldRun('services')) {
            return;
        }
        $this->info('Importing services...');
        $rows = AmeliaServiceModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = ServiceModel::query()->where('amelia_service_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'name' => $a->name,
                        'description' => $a->description,
                        'duration' => (int) ($a->duration ?? 0),
                        'price' => (float) ($a->price ?? 0),
                        'min_capacity' => (int) ($a->minCapacity ?? 1),
                        'max_capacity' => (int) ($a->maxCapacity ?? 1),
                        'status' => ($a->status ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
                    ]);
                }
                $this->counts['services']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['services']++;
                continue;
            }
            ServiceModel::query()->create([
                'amelia_service_id' => $a->id,
                'name' => $a->name ?? 'Service ' . $a->id,
                'description' => $a->description,
                'duration' => (int) ($a->duration ?? 0),
                'price' => (float) ($a->price ?? 0),
                'min_capacity' => (int) ($a->minCapacity ?? 1),
                'max_capacity' => (int) ($a->maxCapacity ?? 1),
                'status' => ($a->status ?? 'visible') === 'hidden' ? 'hidden' : 'visible',
            ]);
            $this->counts['services']++;
        }
    }

    private function importPackages(): void
    {
        if (! $this->shouldRun('packages')) {
            return;
        }
        $this->info('Importing packages...');
        $rows = AmeliaPackageModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = PackageModel::query()->where('amelia_package_id', $a->id)->first();
            $totalSessions = (int) ($a->quantity ?? $a->durationCount ?? 0);
            if ($totalSessions < 1) {
                $totalSessions = 1;
            }
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'title' => $a->name ?? $existing->title,
                        'description' => $a->description,
                        'total_sessions' => $totalSessions,
                        'price' => (float) ($a->price ?? 0),
                        'discount' => (float) ($a->discount ?? 0),
                        'expiry' => $a->endDate,
                        'status' => ($a->status ?? 'visible') === 'hidden' ? 'hidden' : 'active',
                    ]);
                }
                $this->counts['packages']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['packages']++;
                continue;
            }
            PackageModel::query()->create([
                'amelia_package_id' => $a->id,
                'title' => $a->name ?? 'Package ' . $a->id,
                'description' => $a->description,
                'total_sessions' => $totalSessions,
                'price' => (float) ($a->price ?? 0),
                'discount' => (float) ($a->discount ?? 0),
                'expiry' => $a->endDate,
                'status' => ($a->status ?? 'visible') === 'hidden' ? 'hidden' : 'active',
            ]);
            $this->counts['packages']++;
        }
    }

    private function importServiceStaff(): void
    {
        if (! $this->shouldRun('service_staff')) {
            return;
        }
        $this->info('Importing service_staff (providers_to_services)...');
        $rows = AmeliaProviderServiceModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $staff = StaffModel::query()->where('amelia_user_id', $a->userId)->first();
            $service = ServiceModel::query()->where('amelia_service_id', $a->serviceId)->first();
            if (! $staff || ! $service) {
                continue;
            }
            $exists = DB::table('service_staff')
                ->where('service_id', $service->id)
                ->where('staff_id', $staff->id)
                ->exists();
            if ($exists) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                }
                continue;
            }
            if ($this->dryRun) {
                $this->counts['service_staff']++;
                continue;
            }
            DB::table('service_staff')->insert([
                'service_id' => $service->id,
                'staff_id' => $staff->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->counts['service_staff']++;
        }
    }

    private function importPackageService(): void
    {
        if (! $this->shouldRun('package_service')) {
            return;
        }
        $this->info('Importing package_service (packages_to_services)...');
        $rows = AmeliaPackageServiceModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $package = PackageModel::query()->where('amelia_package_id', $a->packageId)->first();
            $service = ServiceModel::query()->where('amelia_service_id', $a->serviceId)->first();
            if (! $package || ! $service) {
                continue;
            }
            $quantity = (int) ($a->quantity ?? 1);
            $exists = DB::table('package_service')
                ->where('package_id', $package->id)
                ->where('service_id', $service->id)
                ->whereNull('provider_id')
                ->whereNull('location_id')
                ->exists();
            if ($exists) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                }
                if (! $this->dryRun) {
                    DB::table('package_service')
                        ->where('package_id', $package->id)
                        ->where('service_id', $service->id)
                        ->whereNull('provider_id')
                        ->whereNull('location_id')
                        ->update(['quantity' => $quantity, 'updated_at' => now()]);
                }
                continue;
            }
            if ($this->dryRun) {
                $this->counts['package_service']++;
                continue;
            }
            DB::table('package_service')->insert([
                'package_id' => $package->id,
                'service_id' => $service->id,
                'provider_id' => null,
                'location_id' => null,
                'quantity' => $quantity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->counts['package_service']++;
        }
    }

    private function importAppointments(): void
    {
        if (! $this->shouldRun('appointments')) {
            return;
        }
        $this->info('Importing appointments...');
        $rows = AmeliaAppointmentModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $service = ServiceModel::query()->where('amelia_service_id', $a->serviceId)->first();
            $provider = StaffModel::query()->where('amelia_user_id', $a->providerId)->first();
            if (! $service || ! $provider) {
                continue;
            }
            $package = $a->packageId
                ? PackageModel::query()->where('amelia_package_id', $a->packageId)->first()
                : null;
            $existing = AppointmentModel::query()->where('amelia_appointment_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'booking_start' => $a->bookingStart,
                        'booking_end' => $a->bookingEnd,
                        'service_id' => $service->id,
                        'provider_id' => $provider->id,
                        'package_id' => $package?->id,
                        'location_id' => $a->locationId,
                        'status' => $this->mapAppointmentStatus($a->status),
                        'internal_notes' => $a->internalNotes,
                    ]);
                }
                $this->counts['appointments']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['appointments']++;
                continue;
            }
            AppointmentModel::query()->create([
                'amelia_appointment_id' => $a->id,
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'package_id' => $package?->id,
                'location_id' => $a->locationId,
                'booking_start' => $a->bookingStart,
                'booking_end' => $a->bookingEnd,
                'status' => $this->mapAppointmentStatus($a->status),
                'internal_notes' => $a->internalNotes,
            ]);
            $this->counts['appointments']++;
        }
    }

    private function mapAppointmentStatus(?string $status): string
    {
        return match ($status) {
            'canceled', 'rejected' => 'canceled',
            'pending' => 'pending',
            default => 'approved',
        };
    }

    private function importBookings(): void
    {
        if (! $this->shouldRun('bookings')) {
            return;
        }
        $this->info('Importing bookings (Amelia customer_bookings)...');
        $rows = AmeliaCustomerBookingModel::on('wordpress')
            ->with('appointment')
            ->get();
        foreach ($rows as $a) {
            $customer = Customer::query()->where('amelia_user_id', $a->customerId)->first();
            if (! $customer) {
                continue;
            }
            $appointment = $a->appointment
                ? AppointmentModel::query()->where('amelia_appointment_id', $a->appointmentId)->first()
                : null;
            $existing = BookingModel::query()->where('amelia_customer_booking_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun && $appointment) {
                    $existing->update([
                        'appointment_id' => $appointment->id,
                        'status' => $this->mapBookingStatus($a->status),
                        'total_amount' => (float) ($a->price ?? 0),
                        'party_size' => (int) ($a->persons ?? 1),
                        'booked_at' => $a->created ?? $a->appointment?->bookingStart ?? now(),
                    ]);
                }
                $this->counts['bookings']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['bookings']++;
                continue;
            }
            $bookedAt = $a->created ?? $a->appointment?->bookingStart ?? now();
            BookingModel::query()->create([
                'amelia_customer_booking_id' => $a->id,
                'appointment_id' => $appointment?->id,
                'event_id' => null,
                'event_instance_id' => null,
                'customer_id' => $customer->id,
                'service_id' => $appointment?->service_id,
                'provider_id' => $appointment?->provider_id,
                'package_id' => $appointment?->package_id,
                'location_id' => $appointment?->location_id,
                'status' => $this->mapBookingStatus($a->status),
                'payment_status' => 'pending',
                'party_size' => (int) ($a->persons ?? 1),
                'total_amount' => (float) ($a->price ?? 0),
                'deposit_amount' => 0,
                'balance_amount' => (float) ($a->price ?? 0),
                'currency' => 'USD',
                'channel' => 'amelia_import',
                'booked_at' => $bookedAt,
            ]);
            $this->counts['bookings']++;
        }
    }

    private function mapBookingStatus(?string $status): string
    {
        return match ($status) {
            'canceled', 'rejected' => 'cancelled',
            'approved' => 'confirmed',
            'pending' => 'pending',
            default => 'pending',
        };
    }
}
