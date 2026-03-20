<?php

namespace App\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MigrateFromFirestoreCommand extends Command
{
    protected $signature = 'firestore:migrate
                            {--purchased-packages= : Path to purchased_packages.json (Firestore purchasedPackages collection)}
                            {--payment-bookings= : Path to payment_bookings.json (Firestore payment/booking collection)}
                            {--dry-run : Only report what would be done, do not write}
                            {--skip-duplicates : Skip records that already exist (e.g. by transaction_id)}';

    protected $description = 'Migrate Firestore data (purchasedPackages, payment/bookings) into Laravel DB';

    private bool $dryRun = false;
    private bool $skipDuplicates = false;
    private int $customersCreated = 0;
    private int $customersUpdated = 0;
    private int $packagePurchasesCreated = 0;
    private int $bookingsCreated = 0;
    private int $paymentsCreated = 0;
    private int $skipped = 0;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->skipDuplicates = (bool) $this->option('skip-duplicates');

        $purchasedPath = $this->option('purchased-packages');
        $paymentPath = $this->option('payment-bookings');

        if (! $purchasedPath && ! $paymentPath) {
            $this->error('Provide at least one of: --purchased-packages=path or --payment-bookings=path');
            $this->line('Example: php artisan firestore:migrate --purchased-packages=storage/purchased_packages.json --payment-bookings=storage/payment_bookings.json');
            return self::FAILURE;
        }

        if ($this->dryRun) {
            $this->warn('Dry run: no changes will be written.');
        }

        if ($purchasedPath) {
            if (! is_file($purchasedPath)) {
                $this->error("File not found: {$purchasedPath}");
                return self::FAILURE;
            }
            $this->migratePurchasedPackages($purchasedPath);
        }

        if ($paymentPath) {
            if (! is_file($paymentPath)) {
                $this->error("File not found: {$paymentPath}");
                return self::FAILURE;
            }
            $this->migratePaymentBookings($paymentPath);
        }

        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Action', 'Count'],
            [
                ['Customers created', $this->customersCreated],
                ['Customers updated', $this->customersUpdated],
                ['Package purchases created', $this->packagePurchasesCreated],
                ['Bookings created', $this->bookingsCreated],
                ['Payments created', $this->paymentsCreated],
                ['Skipped (duplicates)', $this->skipped],
            ]
        );

        return self::SUCCESS;
    }

    private function migratePurchasedPackages(string $path): void
    {
        $data = $this->readJson($path);
        if ($data === null) {
            return;
        }

        $items = is_array($data) ? $data : (isset($data['documents']) ? $data['documents'] : []);
        if (empty($items)) {
            $this->warn('No documents in purchased packages file.');
            return;
        }

        $this->info('Migrating ' . count($items) . ' purchased package record(s)...');

        foreach ($items as $doc) {
            $fields = $this->extractFields($doc);
            $uid = $fields['uid'] ?? $fields['firebase_uid'] ?? null;
            if (empty($uid)) {
                $this->warn('Skipping doc: missing uid');
                continue;
            }

            $customer = Customer::query()->where('uid', $uid)->orWhere('firebase_uid', $uid)->first();
            if (! $customer) {
                if (! $this->dryRun) {
                    $customer = Customer::query()->create([
                        'uid' => $uid,
                        'firebase_uid' => $uid,
                        'first_name' => $fields['firstName'] ?? $fields['first_name'] ?? 'Customer',
                        'last_name' => $fields['lastName'] ?? $fields['last_name'] ?? null,
                        'email' => $fields['email'] ?? null,
                        'phone' => $fields['phone'] ?? null,
                        'phone_verified_at' => ! empty($fields['isVerified']) ? now() : null,
                    ]);
                    $this->customersCreated++;
                } else {
                    $this->customersCreated++;
                    continue;
                }
            } else {
                if (! $this->dryRun) {
                    $customer->fill([
                        'first_name' => $fields['firstName'] ?? $customer->first_name,
                        'last_name' => $fields['lastName'] ?? $customer->last_name,
                        'email' => $fields['email'] ?? $customer->email,
                        'phone' => $fields['phone'] ?? $customer->phone,
                        'phone_verified_at' => ! empty($fields['isVerified']) ? ($customer->phone_verified_at ?? now()) : $customer->phone_verified_at,
                    ])->save();
                    $this->customersUpdated++;
                }
            }

            $totalSessions = (int) ($fields['packages'] ?? $fields['total_sessions'] ?? 0);
            $remainingSessions = (int) ($fields['remainingClasses'] ?? $fields['remaining_sessions'] ?? $totalSessions);
            $purchaseDate = $this->parseTimestamp($fields['purchaseDate'] ?? $fields['createdAt'] ?? $fields['purchase_date'] ?? null);
            $ameliaPackageId = (int) ($fields['packageId'] ?? $fields['package_id'] ?? 0) ?: null;
            $ameliaPackageCustomerId = (int) ($fields['packageCustomerId'] ?? $fields['package_customer_id'] ?? 0) ?: null;

            if ($this->dryRun) {
                $this->packagePurchasesCreated++;
                continue;
            }

            DB::transaction(function () use (
                $customer,
                $totalSessions,
                $remainingSessions,
                $purchaseDate,
                $ameliaPackageId,
                $ameliaPackageCustomerId
            ): void {
                $packageId = null;
                if ($ameliaPackageId !== null) {
                    $packageId = PackageModel::query()
                        ->where('amelia_package_id', (int) $ameliaPackageId)
                        ->value('id');
                }

                $purchasePayload = [
                    'customer_id' => $customer->id,
                    'package_id' => $packageId,
                    'amelia_package_id' => $ameliaPackageId,
                    'total_sessions' => $totalSessions ?: 1,
                    'remaining_sessions' => $remainingSessions,
                    'purchase_date' => $purchaseDate ?? now(),
                    'status' => 'active',
                    'amelia_package_customer_id' => $ameliaPackageCustomerId,
                ];

                // Re-runnable: upsert by amelia_package_customer_id when available.
                if ($ameliaPackageCustomerId !== null) {
                    $existing = CustomerPackagePurchaseModel::query()
                        ->where('amelia_package_customer_id', $ameliaPackageCustomerId)
                        ->first();

                    if ($existing) {
                        $existing->fill($purchasePayload)->save();
                        return;
                    }
                }

                // Fallback upsert when purchase source doesn't include packageCustomerId.
                // Note: relies on purchase_date precision being stable in the exported JSON.
                $existingFallback = CustomerPackagePurchaseModel::query()
                    ->where('customer_id', $customer->id)
                    ->where('purchase_date', $purchasePayload['purchase_date'])
                    ->where('total_sessions', $purchasePayload['total_sessions'])
                    ->where('remaining_sessions', $purchasePayload['remaining_sessions'])
                    ->where('amelia_package_id', $ameliaPackageId)
                    ->first();

                if ($existingFallback) {
                    $existingFallback->fill($purchasePayload)->save();
                    return;
                }

                CustomerPackagePurchaseModel::query()->create($purchasePayload);
                $this->packagePurchasesCreated++;
            });
        }
    }

    private function migratePaymentBookings(string $path): void
    {
        $data = $this->readJson($path);
        if ($data === null) {
            return;
        }

        $items = is_array($data) ? $data : (isset($data['documents']) ? $data['documents'] : []);
        if (empty($items)) {
            $this->warn('No documents in payment bookings file.');
            return;
        }

        $this->info('Migrating ' . count($items) . ' payment/booking record(s)...');

        foreach ($items as $doc) {
            $fields = $this->extractFields($doc);

            $transactionId = $fields['transactionId'] ?? $fields['transaction_id'] ?? null;
            $isDuplicate = $this->skipDuplicates && $transactionId
                && PaymentModel::query()->where('transaction_id', $transactionId)->exists();

            $customer = $this->resolveCustomerForPaymentBooking($fields);
            if (! $customer) {
                if (! $this->dryRun) {
                    $fullName = (string) ($fields['userName'] ?? $fields['user_name'] ?? 'Customer');
                    $parts = explode(' ', trim($fullName), 2);
                    $customer = Customer::query()->create([
                        'first_name' => $parts[0] ?: 'Customer',
                        'last_name' => $parts[1] ?? null,
                        'email' => $fields['userEmail'] ?? $fields['user_email'] ?? null,
                        'phone' => $fields['userPhone'] ?? $fields['user_phone'] ?? null,
                        'external_id' => (string) ($fields['userId'] ?? $fields['user_id'] ?? Str::uuid()),
                    ]);
                    $this->customersCreated++;
                } else {
                    $this->bookingsCreated++;
                    $this->paymentsCreated++;
                    continue;
                }
            } else {
                // purchasedPackages export may have created placeholder customers (e.g. name = "Customer").
                // When we have real name/email/phone from paymentBookings, update existing customer records.
                if (! $this->dryRun) {
                    $updated = $this->updateCustomerFromPaymentFields($customer, $fields);
                    if ($updated) {
                        $this->customersUpdated++;
                    }
                }
            }

            // Even when skipping duplicates, we may still want to update customer identity fields
            // (name/email/phone) from payment data.
            if ($isDuplicate) {
                $this->skipped++;
                continue;
            }

            $bookedAt = $this->parseTimestamp($fields['bookingDate'] ?? $fields['createdAt'] ?? $fields['booked_at'] ?? null);
            $amount = (float) (preg_replace('/[^0-9.]/', '', (string) ($fields['classPrice'] ?? $fields['class_price'] ?? 0)) ?: 0);
            $status = $fields['status'] ?? 'pending';
            $paymentStatus = $fields['paymentStatus'] ?? $fields['payment_status'] ?? 'pending';

            if ($this->dryRun) {
                $this->bookingsCreated++;
                $this->paymentsCreated++;
                continue;
            }

            DB::transaction(function () use ($customer, $fields, $bookedAt, $amount, $status, $paymentStatus, $transactionId): void {
                $booking = BookingModel::query()->create([
                    'event_id' => null,
                    'event_instance_id' => null,
                    'customer_id' => $customer->id,
                    'service_id' => null,
                    'provider_id' => null,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'party_size' => 1,
                    'total_amount' => $amount,
                    'deposit_amount' => 0,
                    'balance_amount' => $amount,
                    'currency' => 'USD',
                    'channel' => 'mobile',
                    'booked_at' => $bookedAt ?? now(),
                    'notes' => $this->paymentBookingNotes($fields),
                ]);
                $this->bookingsCreated++;

                PaymentModel::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'booking_id' => $booking->id,
                    'provider' => 'firestore_migrated',
                    'provider_reference' => $transactionId,
                    'transaction_id' => $transactionId,
                    'status' => $paymentStatus,
                    'amount' => $amount,
                    'currency' => 'USD',
                    'meta' => [
                        'classId' => $fields['classId'] ?? null,
                        'classInstructorId' => $fields['classInstructorId'] ?? null,
                        'className' => $fields['className'] ?? null,
                        'classServiceId' => $fields['classServiceId'] ?? null,
                        'classTime' => $fields['classTime'] ?? null,
                        'classDuration' => $fields['classDuration'] ?? null,
                        'isTestMode' => $fields['isTestMode'] ?? null,
                    ],
                    'paid_at' => $paymentStatus === 'completed' ? ($bookedAt ?? now()) : null,
                ]);
                $this->paymentsCreated++;
            });
        }
    }

    /**
     * Update an existing customer using fields from a payment booking record.
     * This fixes the case where purchasedPackages migration created placeholder customers
     * without name/email/phone, but paymentBookings includes full user data.
     */
    private function updateCustomerFromPaymentFields(Customer $customer, array $fields): bool
    {
        $fullName = (string) ($fields['userName'] ?? $fields['user_name'] ?? '');
        $email = $fields['userEmail'] ?? $fields['user_email'] ?? null;
        $phone = $fields['userPhone'] ?? $fields['user_phone'] ?? null;

        $currentFirst = (string) ($customer->first_name ?? '');
        $currentLast = (string) ($customer->last_name ?? '');

        $parts = $fullName !== '' ? explode(' ', trim($fullName), 2) : [];
        $newFirst = $parts[0] ?? null;
        $newLast = $parts[1] ?? null;

        $shouldUpdateName =
            ($newFirst !== null && $newFirst !== '')
            && ($currentFirst === '' || $currentFirst === 'Customer');

        $shouldUpdateEmail = $email && ($customer->email === null || $customer->email === '');
        $shouldUpdatePhone = $phone && ($customer->phone === null || $customer->phone === '');

        $changed = false;

        if ($shouldUpdateName) {
            $customer->first_name = $newFirst;
            $customer->last_name = ($newLast !== null && $newLast !== '') ? $newLast : null;
            $changed = true;
        } else {
            // If first name is already real, only fill missing last name.
            if (! empty($newLast) && ($currentLast === '' || $currentLast === null)) {
                $customer->last_name = $newLast;
                $changed = true;
            }
        }

        if ($shouldUpdateEmail) {
            $customer->email = $email;
            $changed = true;
        }

        if ($shouldUpdatePhone) {
            $customer->phone = $phone;
            $changed = true;
        }

        if (! $changed) {
            return false;
        }

        $customer->save();
        return true;
    }

    private function resolveCustomerForPaymentBooking(array $fields): ?Customer
    {
        $userId = $fields['userId'] ?? $fields['user_id'] ?? null;
        $email = $fields['userEmail'] ?? $fields['user_email'] ?? null;
        $phone = $fields['userPhone'] ?? $fields['user_phone'] ?? null;

        if (is_numeric($userId)) {
            $customer = Customer::query()->where('amelia_user_id', (int) $userId)->first();
            if ($customer) {
                return $customer;
            }
        }

        if ($email) {
            $customer = Customer::query()->where('email', $email)->first();
            if ($customer) {
                return $customer;
            }
        }

        if ($phone) {
            $customer = Customer::query()->where('phone', $phone)->first();
            if ($customer) {
                return $customer;
            }
        }

        if ($userId && is_string($userId) && ! is_numeric($userId)) {
            $customer = Customer::query()->where('uid', $userId)->orWhere('firebase_uid', $userId)->first();
            if ($customer) {
                return $customer;
            }
        }

        return null;
    }

    private function paymentBookingNotes(array $fields): string
    {
        $parts = [];
        if (! empty($fields['className'])) {
            $parts[] = 'Class: ' . $fields['className'];
        }
        if (! empty($fields['classInstructor'])) {
            $parts[] = 'Instructor: ' . $fields['classInstructor'];
        }
        if (! empty($fields['classDate'])) {
            $parts[] = 'Class date: ' . (is_string($fields['classDate']) ? $fields['classDate'] : json_encode($fields['classDate']));
        }
        return implode(' | ', $parts);
    }

    private function readJson(string $path): ?array
    {
        $content = file_get_contents($path);
        if ($content === false) {
            $this->error("Could not read: {$path}");
            return null;
        }
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: ' . json_last_error_msg());
            return null;
        }
        return $decoded;
    }

    /** @param array|object $doc Firestore doc or flat object */
    private function extractFields($doc): array
    {
        if (is_array($doc)) {
            if (isset($doc['fields'])) {
                $out = [];
                foreach ($doc['fields'] as $key => $value) {
                    $out[$key] = $this->extractFirestoreValue($value);
                }
                return $out;
            }
            return $doc;
        }
        if (is_object($doc)) {
            return (array) $doc;
        }
        return [];
    }

    /** @param array $value Firestore value like { "stringValue": "x" } or { "integerValue": "1" } */
    private function extractFirestoreValue($value)
    {
        if (! is_array($value)) {
            return $value;
        }
        if (isset($value['stringValue'])) {
            return $value['stringValue'];
        }
        if (isset($value['integerValue'])) {
            return (int) $value['integerValue'];
        }
        if (isset($value['doubleValue'])) {
            return (float) $value['doubleValue'];
        }
        if (isset($value['booleanValue'])) {
            return $value['booleanValue'];
        }
        if (isset($value['timestampValue'])) {
            return $this->parseTimestamp($value['timestampValue']);
        }
        if (isset($value['nullValue'])) {
            return null;
        }
        return $value;
    }

    private function parseTimestamp($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestamp((int) $value);
        }
        if (is_array($value) && isset($value['_seconds'])) {
            return Carbon::createFromTimestamp((int) $value['_seconds']);
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }
}
