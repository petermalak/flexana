# WordPress to MySQL Sync Guide

This guide explains how to migrate all data from WordPress/Amelia database to MySQL database, ensuring your system can operate independently without WordPress.

## Overview

Your system now has **automatic sync/cloning** functionality that:
1. **Automatically clones** WordPress data to MySQL whenever it's read
2. **Manual sync commands** to bulk migrate all WordPress data
3. **Ensures MySQL is the primary database** for all operations

---

## Database Configuration

### Primary Database (MySQL)
Your `.env` file should have:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexana
DB_USERNAME=root
DB_PASSWORD=
```

**This is your MAIN database** - all Laravel models use this by default.

### WordPress Database (Temporary)
```env
WP_DB_HOST=127.0.0.1
WP_DB_PORT=3306
WP_DB_DATABASE=cyanboutique_flexana
WP_DB_USERNAME=root
WP_DB_PASSWORD=
```

**This is temporary** - used only for reading/migrating data before deletion.

---

## How Auto-Sync Works

### Automatic Cloning on Read

Whenever your code reads data from WordPress, it **automatically clones** it to MySQL:

1. **AmeliaCustomerResolver** - When resolving customers from WordPress, they're automatically synced to MySQL
2. **Future**: Any code reading from WordPress models will trigger auto-sync

### Example Flow:
```
1. Code reads customer from WordPress → AutoSyncAmeliaService syncs to MySQL
2. Code reads service from WordPress → AutoSyncAmeliaService syncs to MySQL
3. Code reads booking from WordPress → AutoSyncAmeliaService syncs to MySQL (and dependencies)
```

---

## Manual Sync Commands

### 1. Comprehensive Sync (Recommended)

Sync **ALL** WordPress data to MySQL in one command:

```bash
php artisan amelia:sync-to-mysql
```

**Options:**
- `--dry-run` - Preview what would be synced without making changes
- `--skip-duplicates` - Skip records that already exist
- `--only=customers,staff,services` - Sync only specific entities

**Examples:**
```bash
# Preview sync
php artisan amelia:sync-to-mysql --dry-run

# Sync everything
php artisan amelia:sync-to-mysql

# Sync only customers and bookings
php artisan amelia:sync-to-mysql --only=customers,bookings

# Skip records that already exist
php artisan amelia:sync-to-mysql --skip-duplicates
```

**What it syncs:**
- ✅ Customers (`amelia_users` → `customers`)
- ✅ Staff (`amelia_users` → `staff`)
- ✅ Services (`amelia_services` → `services`)
- ✅ Packages (`amelia_packages` → `packages`)
- ✅ Service-Staff relationships (`amelia_providers_to_services` → `service_staff`)
- ✅ Package-Service relationships (`amelia_packages_to_services` → `package_service`)
- ✅ Appointments (`amelia_appointments` → `appointments`)
- ✅ Bookings (`amelia_customer_bookings` → `bookings`)
- ✅ Payments (`amelia_payments` → `payments`)

### 2. Import Command (Enhanced)

The existing import command now uses auto-sync:

```bash
php artisan amelia:import
```

**Options:**
- `--dry-run` - Preview changes
- `--skip-duplicates` - Skip existing records
- `--only=customers,staff,services` - Import only specific entities

**Note:** This command now includes customers and payments sync.

### 3. Sync Customers Only

Sync customers from WordPress to MySQL:

```bash
php artisan amelia:sync-customers
```

---

## Migration Steps

### Step 1: Verify Database Connections

Test both databases are accessible:

```bash
php artisan tinker
```

```php
// Test MySQL (primary)
DB::connection()->getPdo();

// Test WordPress (temporary)
DB::connection('wordpress')->getPdo();
```

### Step 2: Run Comprehensive Sync

Sync all WordPress data to MySQL:

```bash
php artisan amelia:sync-to-mysql
```

This will:
1. Sync all customers, staff, services, packages
2. Sync all relationships (service-staff, package-service)
3. Sync all appointments and bookings
4. Sync all payments

### Step 3: Verify Sync Results

Check that data exists in MySQL:

```bash
php artisan tinker
```

```php
// Check customers
\App\Models\Customer::count();

// Check services
\App\Infrastructure\Persistence\Eloquent\ServiceModel::count();

// Check bookings
\App\Infrastructure\Persistence\Eloquent\BookingModel::count();

// Check payments
\App\Infrastructure\Persistence\Eloquent\PaymentModel::count();
```

### Step 4: Test Your Application

1. Test API endpoints that previously read from WordPress
2. Verify data is now coming from MySQL
3. Check that auto-sync is working when WordPress data is accessed

### Step 5: Remove WordPress Dependency (Future)

Once you're confident everything works:

1. **Remove WordPress connection** from `config/database.php` (optional)
2. **Remove `WP_DB_*` variables** from `.env` (optional)
3. **Delete WordPress database** (when ready)

---

## Data Mapping

### Customers
- `rueyn_amelia_users` (type='customer') → `customers` table
- Linked by: `amelia_user_id` field

### Staff
- `rueyn_amelia_users` (type='provider/manager/admin') → `staff` table
- Linked by: `amelia_user_id` field

### Services
- `rueyn_amelia_services` → `services` table
- Linked by: `amelia_service_id` field

### Packages
- `rueyn_amelia_packages` → `packages` table
- Linked by: `amelia_package_id` field

### Appointments
- `rueyn_amelia_appointments` → `appointments` table
- Linked by: `amelia_appointment_id` field

### Bookings
- `rueyn_amelia_customer_bookings` → `bookings` table
- Linked by: `amelia_customer_booking_id` field

### Payments
- `rueyn_amelia_payments` → `payments` table
- Linked by: `provider_reference` field (stores Amelia payment ID)

---

## Troubleshooting

### WordPress Database Not Reachable

**Error:** `WordPress/Amelia database not reachable`

**Solution:**
1. Check `WP_DB_*` variables in `.env`
2. Verify WordPress database is running
3. Test connection: `DB::connection('wordpress')->getPdo()`

### MySQL Database Not Reachable

**Error:** `MySQL database not reachable`

**Solution:**
1. Check `DB_*` variables in `.env`
2. Verify MySQL is running
3. Ensure database `flexana` exists
4. Test connection: `DB::connection()->getPdo()`

### Missing Dependencies

**Error:** Sync fails because dependencies don't exist

**Solution:**
1. Sync in order: customers/staff → services/packages → appointments → bookings → payments
2. Or use `amelia:sync-to-mysql` which handles dependencies automatically

### Duplicate Records

**Solution:**
- Use `--skip-duplicates` flag to skip existing records
- Or manually clean duplicates before syncing

---

## Best Practices

1. **Always run `--dry-run` first** to preview changes
2. **Backup both databases** before major syncs
3. **Sync during low-traffic periods** for large datasets
4. **Monitor sync progress** for large datasets
5. **Verify data integrity** after sync completes

---

## Future: Removing WordPress Dependency

Once all data is synced and verified:

1. **Update code** to use Laravel models instead of Amelia models
2. **Remove WordPress connection** from `config/database.php`
3. **Remove `WP_DB_*` variables** from `.env`
4. **Delete WordPress database** when ready

Your system will continue to work normally using only MySQL! 🎉

---

## Commands Summary

| Command | Purpose |
|---------|---------|
| `php artisan amelia:sync-to-mysql` | Sync ALL WordPress data to MySQL |
| `php artisan amelia:import` | Import WordPress data (enhanced with auto-sync) |
| `php artisan amelia:sync-customers` | Sync customers only |

---

## Support

If you encounter issues:
1. Check logs: `storage/logs/laravel.log`
2. Run with `--dry-run` to preview changes
3. Verify database connections
4. Check that all dependencies are synced first
