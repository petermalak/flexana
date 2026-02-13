<?php

namespace App\Console\Commands;

use App\Application\Amelia\Services\AutoSyncAmeliaService;
use App\Infrastructure\Persistence\Eloquent\AmeliaAppointmentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaCustomerBookingModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaEventModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaEventPeriodModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaEventTicketModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaPaymentModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaProviderServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Models\Category;
use App\Models\Customer;
use App\Models\EventPeriod;
use App\Models\EventTicket;
use App\Models\Location;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\BookableResource;
use App\Models\Event;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fetch all Amelia (WordPress) data into Laravel MySQL. Safe for staging and production.
 * Configure WP_DB_* in .env to point at the WordPress/Amelia database.
 */
class FetchAmeliaCommand extends Command
{
    protected $signature = 'amelia:fetch
                            {--dry-run : Only report what would be done, no writes}
                            {--skip-duplicates : Skip records that already exist (by amelia_* id)}
                            {--only=* : Limit to entities: locations,customers,staff,services,packages,service-staff,package-service,appointments,bookings,payments,events,event-periods,event-tickets,categories,tags,resources,settings}';

    protected $description = 'Fetch all Amelia (WordPress) DB data into Laravel MySQL. Idempotent: no duplicates (upsert by Amelia id). Set WP_DB_* in .env.';

    private bool $dryRun = false;
    private bool $skipDuplicates = false;
    private array $only = [];
    private array $counts = [];

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
        $this->counts = array_fill_keys([
            'locations', 'customers', 'staff', 'services', 'packages', 'service_staff', 'package_service',
            'appointments', 'bookings', 'payments', 'events', 'event_periods', 'event_tickets',
            'categories', 'tags', 'resources', 'settings', 'skipped', 'errors',
        ], 0);

        $wpConfig = config('database.connections.wordpress');
        if (! $wpConfig) {
            $this->error('WordPress connection is not configured. Add WP_DB_HOST, WP_DB_DATABASE, WP_DB_USERNAME, WP_DB_PASSWORD (and optional WP_DB_PREFIX) to .env');
            return self::FAILURE;
        }

        $wpDb = $wpConfig['database'] ?? null;
        if (empty($wpDb)) {
            $this->error('WP_DB_DATABASE is not set in .env. Set it to your WordPress/Amelia database name (must be different from Laravel DB if Amelia is in WordPress).');
            return self::FAILURE;
        }

        try {
            DB::connection('wordpress')->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to WordPress/Amelia database: ' . $e->getMessage());
            $this->line('Check WP_DB_HOST, WP_DB_DATABASE, WP_DB_USERNAME, WP_DB_PASSWORD in .env');
            return self::FAILURE;
        }

        $prefix = $wpConfig['prefix'] ?? '';
        $this->line('Using WordPress DB: <info>' . $wpDb . '</info>, table prefix: <info>' . ($prefix !== '' ? $prefix : '(none)') . '</info>');
        if (empty($prefix)) {
            $this->warn('WP_DB_PREFIX is empty. If Amelia tables are prefixed (e.g. rueyn_amelia_users), set WP_DB_PREFIX=rueyn_amelia_ in .env and run: php artisan config:clear');
        }

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to Laravel MySQL database: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run: no data will be written.');
        }

        $this->info('Fetching Amelia data into MySQL...');
        $this->newLine();

        $entities = [
            'locations' => fn () => $this->fetchLocations(),
            'customers' => fn () => $this->fetchCustomers(),
            'staff' => fn () => $this->fetchStaff(),
            'services' => fn () => $this->fetchServices(),
            'packages' => fn () => $this->fetchPackages(),
            'service-staff' => fn () => $this->fetchServiceStaff(),
            'package-service' => fn () => $this->fetchPackageService(),
            'appointments' => fn () => $this->fetchAppointments(),
            'bookings' => fn () => $this->fetchBookings(),
            'payments' => fn () => $this->fetchPayments(),
            'events' => fn () => $this->fetchEvents(),
            'event-periods' => fn () => $this->fetchEventPeriods(),
            'event-tickets' => fn () => $this->fetchEventTickets(),
            'categories' => fn () => $this->fetchCategories(),
            'tags' => fn () => $this->fetchTags(),
            'resources' => fn () => $this->fetchResources(),
            'settings' => fn () => $this->fetchSettings(),
        ];

        foreach ($entities as $key => $callable) {
            if (! $this->shouldRun($key)) {
                continue;
            }
            try {
                $callable();
            } catch (\Throwable $e) {
                $this->counts['errors']++;
                $this->error("  Error in {$key}: " . $e->getMessage());
                if ($this->output->isVerbose()) {
                    $this->line($e->getTraceAsString());
                }
            }
        }

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Entity', 'Count'],
            collect($this->counts)->filter(fn ($v) => $v > 0)->map(fn ($v, $k) => [str_replace('_', ' ', $k), $v])->values()->all()
        );

        if ($this->counts['errors'] > 0) {
            $this->warn("Completed with {$this->counts['errors']} entity error(s). Check logs.");
        }

        return self::SUCCESS;
    }

    private function shouldRun(string $key): bool
    {
        if (empty($this->only)) {
            return true;
        }
        $normalized = str_replace('-', '_', $key);
        return in_array($key, $this->only, true) || in_array($normalized, $this->only, true);
    }

    private function fetchLocations(): void
    {
        $this->info('Fetching locations...');
        if (! $this->tableExists('wordpress', 'locations')) {
            $this->line('  Table locations not found in WordPress DB (optional). Skipping.');
            return;
        }
        $rows = DB::connection('wordpress')->table('locations')->get();
        foreach ($rows as $row) {
            $id = $row->id ?? $row->ID ?? null;
            $name = $row->name ?? $row->address ?? 'Location ' . $id;
            if (!$id) {
                continue;
            }
            $existing = Location::query()->where('amelia_location_id', $id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'name' => $name,
                        'address' => $row->address ?? $existing->address,
                        'phone' => $row->phone ?? $existing->phone,
                        'latitude' => $row->latitude ?? $existing->latitude,
                        'longitude' => $row->longitude ?? $existing->longitude,
                        'status' => true,
                    ]);
                }
                $this->counts['locations']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['locations']++;
                continue;
            }
            Location::query()->create([
                'amelia_location_id' => $id,
                'name' => $name,
                'address' => $row->address ?? null,
                'phone' => $row->phone ?? null,
                'latitude' => $row->latitude ?? null,
                'longitude' => $row->longitude ?? null,
                'status' => true,
            ]);
            $this->counts['locations']++;
        }
        $this->line("  → {$this->counts['locations']} locations");
    }

    private function fetchCustomers(): void
    {
        $this->info('Fetching customers...');
        if (! $this->tableExists('wordpress', 'users')) {
            $this->line('  Table users not found in WordPress DB. Set WP_DB_DATABASE to the WordPress DB and WP_DB_PREFIX if Amelia uses a prefix (e.g. wp_amelia_). Skipping.');
            return;
        }
        $rows = AmeliaUserModel::on('wordpress')->where('type', 'customer')->get();
        foreach ($rows as $a) {
            $existing = Customer::query()->where('amelia_user_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncCustomer($a);
                }
                $this->counts['customers']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['customers']++;
                continue;
            }
            $this->autoSync->syncCustomer($a);
            $this->counts['customers']++;
        }
        $this->line("  → {$this->counts['customers']} customers");
    }

    private function fetchStaff(): void
    {
        $this->info('Fetching staff...');
        if (! $this->tableExists('wordpress', 'users')) {
            $this->line('  Table users not found in WordPress DB. Set WP_DB_DATABASE and WP_DB_PREFIX if needed. Skipping.');
            return;
        }
        $rows = AmeliaUserModel::on('wordpress')->whereIn('type', ['provider', 'manager', 'admin'])->get();
        foreach ($rows as $a) {
            $existing = StaffModel::query()->where('amelia_user_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncStaff($a);
                }
                $this->counts['staff']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['staff']++;
                continue;
            }
            $this->autoSync->syncStaff($a);
            $this->counts['staff']++;
        }
        $this->line("  → {$this->counts['staff']} staff");
    }

    private function fetchServices(): void
    {
        $this->info('Fetching services...');
        if (! $this->tableExists('wordpress', 'services')) {
            $this->line('  Table services not found in WordPress DB. Set WP_DB_DATABASE and WP_DB_PREFIX if needed. Skipping.');
            return;
        }
        $rows = AmeliaServiceModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = ServiceModel::query()->where('amelia_service_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncService($a);
                }
                $this->counts['services']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['services']++;
                continue;
            }
            $this->autoSync->syncService($a);
            $this->counts['services']++;
        }
        $this->line("  → {$this->counts['services']} services");
    }

    private function fetchPackages(): void
    {
        $this->info('Fetching packages...');
        if (! $this->tableExists('wordpress', 'packages')) {
            $this->line('  Table packages not found in WordPress DB. Set WP_DB_DATABASE and WP_DB_PREFIX if needed. Skipping.');
            return;
        }
        $rows = AmeliaPackageModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = PackageModel::query()->where('amelia_package_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncPackage($a);
                }
                $this->counts['packages']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['packages']++;
                continue;
            }
            $this->autoSync->syncPackage($a);
            $this->counts['packages']++;
        }
        $this->line("  → {$this->counts['packages']} packages");
    }

    private function fetchServiceStaff(): void
    {
        $this->info('Fetching service-staff...');
        if (! $this->tableExists('wordpress', 'providers_to_services')) {
            $this->line('  Table providers_to_services not found. Skipping.');
            return;
        }
        $rows = AmeliaProviderServiceModel::on('wordpress')->get();
        foreach ($rows as $a) {
            if ($this->dryRun) {
                $this->counts['service_staff']++;
                continue;
            }
            $this->autoSync->syncServiceStaff($a->userId, $a->serviceId);
            $this->counts['service_staff']++;
        }
        $this->line("  → {$this->counts['service_staff']} service-staff");
    }

    private function fetchPackageService(): void
    {
        $this->info('Fetching package-service...');
        if (! $this->tableExists('wordpress', 'packages_to_services')) {
            $this->line('  Table packages_to_services not found. Skipping.');
            return;
        }
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
        $this->line("  → {$this->counts['package_service']} package-service");
    }

    private function fetchAppointments(): void
    {
        $this->info('Fetching appointments...');
        if (! $this->tableExists('wordpress', 'appointments')) {
            $this->line('  Table appointments not found in WordPress DB. Set WP_DB_DATABASE and WP_DB_PREFIX if needed. Skipping.');
            return;
        }
        $rows = AmeliaAppointmentModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = AppointmentModel::query()->where('amelia_appointment_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncAppointment($a);
                }
                $this->counts['appointments']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['appointments']++;
                continue;
            }
            $this->autoSync->syncAppointment($a);
            $this->counts['appointments']++;
        }
        $this->line("  → {$this->counts['appointments']} appointments");
    }

    private function fetchBookings(): void
    {
        $this->info('Fetching bookings...');
        if (! $this->tableExists('wordpress', 'customer_bookings')) {
            $this->line('  Table customer_bookings not found. Skipping.');
            return;
        }
        $rows = AmeliaCustomerBookingModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $existing = BookingModel::query()->where('amelia_customer_booking_id', $a->id)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $this->autoSync->syncBooking($a);
                }
                $this->counts['bookings']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['bookings']++;
                continue;
            }
            $this->autoSync->syncBooking($a);
            $this->counts['bookings']++;
        }
        $this->line("  → {$this->counts['bookings']} bookings");
    }

    private function fetchPayments(): void
    {
        $this->info('Fetching payments...');
        if (! $this->tableExists('wordpress', 'payments')) {
            $this->line('  Table payments not found. Skipping.');
            return;
        }
        $rows = AmeliaPaymentModel::on('wordpress')->get();
        foreach ($rows as $a) {
            if ($this->dryRun) {
                $this->counts['payments']++;
                continue;
            }
            $this->autoSync->syncPayment($a);
            $this->counts['payments']++;
        }
        $this->line("  → {$this->counts['payments']} payments");
    }

    private function fetchEvents(): void
    {
        $this->info('Fetching events...');
        if (! $this->tableExists('wordpress', 'events')) {
            $this->line('  Table events not found. Skipping.');
            return;
        }
        $rows = AmeliaEventModel::on('wordpress')->get();
        foreach ($rows as $a) {
            $slug = 'amelia-event-' . $a->id;
            $existing = Event::query()->where('slug', $slug)->first();
            if ($existing) {
                if ($this->skipDuplicates) {
                    $this->counts['skipped']++;
                    continue;
                }
                if (! $this->dryRun) {
                    $existing->update([
                        'name' => $a->name ?? $existing->name,
                        'status' => in_array($a->status ?? '', ['hidden', 'disabled']) ? 'archived' : 'published',
                        'description' => $a->description ?? $existing->description,
                        'capacity' => $a->maxCapacity ?? $existing->capacity,
                        'price' => (float) ($a->price ?? 0),
                    ]);
                }
                $this->counts['events']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['events']++;
                continue;
            }
            Event::query()->create([
                'uuid' => (string) Str::uuid(),
                'slug' => $slug,
                'name' => $a->name ?? 'Event ' . $a->id,
                'status' => in_array($a->status ?? '', ['hidden', 'disabled']) ? 'archived' : 'published',
                'description' => $a->description ?? null,
                'capacity' => $a->maxCapacity ?? null,
                'price' => (float) ($a->price ?? 0),
            ]);
            $this->counts['events']++;
        }
        $this->line("  → {$this->counts['events']} events");
    }

    private function fetchEventPeriods(): void
    {
        $this->info('Fetching event periods...');
        if (! $this->tableExists('wordpress', 'events_periods')) {
            $this->line('  Table events_periods not found. Skipping.');
            return;
        }
        $rows = AmeliaEventPeriodModel::on('wordpress')->get();
        $eventIdMap = $this->getAmeliaEventIdToLaravelIdMap();
        foreach ($rows as $a) {
            $ourEventId = $eventIdMap[$a->eventId ?? 0] ?? null;
            if (! $ourEventId) {
                continue;
            }
            $existing = EventPeriod::query()->where('event_id', $ourEventId)
                ->where('period_start', $a->periodStart ?? $a->period_start ?? null)->first();
            if ($existing && $this->skipDuplicates) {
                $this->counts['skipped']++;
                continue;
            }
            if ($this->dryRun) {
                $this->counts['event_periods']++;
                continue;
            }
            EventPeriod::query()->updateOrCreate(
                [
                    'event_id' => $ourEventId,
                    'period_start' => $a->periodStart ?? $a->period_start,
                ],
                [
                    'period_end' => $a->periodEnd ?? $a->period_end,
                    'zoom_meeting' => is_array($a->zoomMeeting ?? null) ? $a->zoomMeeting : ($a->lesson_space ?? null),
                    'lesson_space' => is_array($a->lessonSpace ?? null) ? $a->lessonSpace : null,
                    'google_calendar_event_id' => $a->googleCalendarEventId ?? null,
                    'google_meet_url' => $a->googleMeetUrl ?? null,
                    'outlook_calendar_event_id' => $a->outlookCalendarEventId ?? null,
                    'microsoft_teams_url' => $a->microsoftTeamsUrl ?? null,
                    'apple_calendar_event_id' => $a->appleCalendarEventId ?? null,
                ]
            );
            $this->counts['event_periods']++;
        }
        $this->line("  → {$this->counts['event_periods']} event periods");
    }

    private function fetchEventTickets(): void
    {
        $this->info('Fetching event tickets...');
        if (! $this->tableExists('wordpress', 'events_to_tickets')) {
            $this->line('  Table events_to_tickets not found. Skipping.');
            return;
        }
        $rows = AmeliaEventTicketModel::on('wordpress')->get();
        $eventIdMap = $this->getAmeliaEventIdToLaravelIdMap();
        foreach ($rows as $a) {
            $ourEventId = $eventIdMap[$a->eventId ?? 0] ?? null;
            if (! $ourEventId) {
                continue;
            }
            if ($this->dryRun) {
                $this->counts['event_tickets']++;
                continue;
            }
            EventTicket::query()->updateOrCreate(
                [
                    'event_id' => $ourEventId,
                    'name' => $a->name ?? 'Ticket ' . $a->id,
                ],
                [
                    'price' => (float) ($a->price ?? 0),
                    'spots' => $a->spots ?? null,
                    'waiting_list_spots' => (int) ($a->waitingListSpots ?? 0),
                    'enabled' => (bool) ($a->enabled ?? true),
                    'date_ranges' => is_array($a->dateRanges ?? null) ? $a->dateRanges : null,
                    'translations' => is_array($a->translations ?? null) ? $a->translations : null,
                ]
            );
            $this->counts['event_tickets']++;
        }
        $this->line("  → {$this->counts['event_tickets']} event tickets");
    }

    private function getAmeliaEventIdToLaravelIdMap(): array
    {
        $map = [];
        $events = Event::query()->get();
        foreach ($events as $e) {
            if (preg_match('/^amelia-event-(\d+)$/', $e->slug ?? '', $m)) {
                $map[(int) $m[1]] = $e->id;
            }
        }
        return $map;
    }

    private function fetchCategories(): void
    {
        $this->info('Fetching categories...');
        if (! $this->tableExists('wordpress', 'categories')) {
            $this->line('  Table categories not found. Skipping.');
            return;
        }
        $rows = DB::connection('wordpress')->table('categories')->get();
        foreach ($rows as $row) {
            $id = $row->id ?? $row->ID ?? null;
            $name = $row->name ?? $row->title ?? 'Category ' . $id;
            if ($id === null) {
                continue;
            }
            $slug = Str::slug($name);
            if ($this->dryRun) {
                $this->counts['categories']++;
                continue;
            }
            $existing = Category::query()->where('amelia_category_id', $id)->first()
                ?? Category::query()->where('slug', $slug)->first();
            if ($existing) {
                $existing->update([
                    'amelia_category_id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                    'type' => 'service',
                    'status' => true,
                ]);
            } else {
                Category::query()->create([
                    'amelia_category_id' => $id,
                    'name' => $name,
                    'slug' => $slug,
                    'type' => 'service',
                    'status' => true,
                ]);
            }
            $this->counts['categories']++;
        }
        $this->line("  → {$this->counts['categories']} categories");
    }

    private function fetchTags(): void
    {
        $this->info('Fetching tags...');
        if (! $this->tableExists('wordpress', 'tags')) {
            $this->line('  Table tags not found. Skipping.');
            return;
        }
        $rows = DB::connection('wordpress')->table('tags')->get();
        foreach ($rows as $row) {
            $name = $row->name ?? $row->title ?? 'Tag ' . ($row->id ?? $row->ID ?? '');
            if ($this->dryRun) {
                $this->counts['tags']++;
                continue;
            }
            Tag::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
            $this->counts['tags']++;
        }
        $this->line("  → {$this->counts['tags']} tags");
    }

    private function fetchResources(): void
    {
        $this->info('Fetching resources...');
        if (! $this->tableExists('wordpress', 'resources')) {
            $this->line('  Table resources not found. Skipping.');
            return;
        }
        $rows = DB::connection('wordpress')->table('resources')->get();
        foreach ($rows as $row) {
            $id = $row->id ?? $row->ID ?? null;
            $name = $row->name ?? $row->title ?? 'Resource ' . $id;
            if ($this->dryRun) {
                $this->counts['resources']++;
                continue;
            }
            $attrs = ['quantity' => $row->quantity ?? 1, 'status' => true];
            $existing = $id !== null
                ? BookableResource::query()->where('amelia_resource_id', $id)->first()
                : null;
            $existing = $existing ?? BookableResource::query()->where('name', $name)->first();
            if ($existing) {
                $existing->update(array_merge(
                    ['name' => $name],
                    $id !== null ? ['amelia_resource_id' => $id] : [],
                    $attrs
                ));
            } else {
                BookableResource::query()->create(array_merge(
                    ['name' => $name],
                    $id !== null ? ['amelia_resource_id' => $id] : [],
                    $attrs
                ));
            }
            $this->counts['resources']++;
        }
        $this->line("  → {$this->counts['resources']} resources");
    }

    private function fetchSettings(): void
    {
        $this->info('Fetching settings...');
        if (! $this->tableExists('wordpress', 'settings')) {
            $this->line('  Table settings not found. Skipping.');
            return;
        }
        $rows = DB::connection('wordpress')->table('settings')->get();
        foreach ($rows as $row) {
            $key = $row->key ?? $row->option_name ?? null;
            $value = $row->value ?? $row->option_value ?? null;
            if (!$key) {
                continue;
            }
            if ($this->dryRun) {
                $this->counts['settings']++;
                continue;
            }
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['group' => $row->group ?? 'general', 'value' => $value ?? '', 'type' => 'string']
            );
            $this->counts['settings']++;
        }
        $this->line("  → {$this->counts['settings']} settings");
    }

    private function tableExists(string $connection, string $table): bool
    {
        try {
            return DB::connection($connection)->getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }
}
