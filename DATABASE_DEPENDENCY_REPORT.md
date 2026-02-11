# Database Dependency Report

**Generated:** February 11, 2026

## Summary

✅ **Your system is NOT dependent on the WordPress database for core operations.**

The system uses **two separate database connections**:
1. **Default Laravel Database** - Used for all Laravel application data
2. **WordPress Database** - Used ONLY for reading Amelia booking data (optional)

---

## Current Database Configuration

### Default Database Connection
- **Connection Name:** `sqlite` (configured via `DB_CONNECTION` in `.env`)
- **Database:** `D:\Projects\Emilia Backend\flexana\database\database.sqlite`
- **Purpose:** Laravel's own database for application data

### WordPress Database Connection
- **Connection Name:** `wordpress`
- **Database:** `cyanboutique_flexana` (configured via `WP_DB_DATABASE` in `.env`)
- **Purpose:** Read-only access to Amelia booking tables (optional integration)

---

## Models Using Each Database

### ✅ Laravel Models (Default Database)
These models use the **default Laravel database** and are **independent** of WordPress:

**Location:** `app/Models/` and `app/Infrastructure/Persistence/Eloquent/`

- `BookingModel` → `bookings` table
- `CustomerModel` → `customers` table
- `ServiceModel` → `services` table
- `StaffModel` → `staff` table
- `PackageModel` → `packages` table
- `EventModel` → `events` table
- `EventInstanceModel` → `event_instances` table
- `PaymentModel` → `payments` table
- `UserModel` → `users` table (Laravel admin users)
- `ApiKeyModel` → `api_keys` table
- `BannerModel` → `banners` table
- `PromoCodeModel` → `promo_codes` table
- `ClassTypeModel` → `class_types` table
- All other Laravel models

**These models do NOT have `protected $connection = 'wordpress'`**, so they use the default connection.

### ⚠️ WordPress/Amelia Models (WordPress Database)
These models use the **WordPress database** and are used for **reading** Amelia data:

**Location:** `app/Infrastructure/Persistence/Eloquent/`

- `AmeliaUserModel` → `rueyn_amelia_users` table
- `AmeliaServiceModel` → `rueyn_amelia_services` table
- `AmeliaAppointmentModel` → `rueyn_amelia_appointments` table
- `AmeliaCustomerBookingModel` → `rueyn_amelia_customer_bookings` table
- `AmeliaPackageModel` → `rueyn_amelia_packages` table
- `AmeliaPaymentModel` → `rueyn_amelia_payments` table
- `AmeliaEventModel` → `rueyn_amelia_events` table
- `AmeliaProviderServiceModel` → `rueyn_amelia_providers_to_services` table
- `AmeliaPackageServiceModel` → `rueyn_amelia_packages_to_services` table
- `AmeliaEventPeriodModel` → `rueyn_amelia_events_periods` table
- `AmeliaEventTicketModel` → `rueyn_amelia_events_to_tickets` table

**All these models have `protected $connection = 'wordpress'`**, explicitly using the WordPress connection.

---

## Where WordPress Connection is Used

### 1. Mobile API (Optional)
Some API endpoints read from WordPress/Amelia tables:
- `BookingController` - May read Amelia bookings
- `AmeliaCustomerResolver` - Resolves customers from Amelia

**Note:** These are for **reading** Amelia data, not core system functionality.

### 2. Filament Admin Panel (Optional)
Filament resources for viewing Amelia data:
- `AmeliaBookingResource`
- `AmeliaCustomerResource`
- `AmeliaServiceResource`

**Note:** These are for **viewing** Amelia data, not core system functionality.

### 3. Migration/Import Commands (Optional)
- `ImportAmeliaCommand` - Imports Amelia data into Laravel database
- `SyncAmeliaCustomersCommand` - Syncs Amelia customers with Laravel customers

**Note:** These are **one-time migration tools**, not core system dependencies.

---

## Core System Independence

### ✅ Core Features Use Laravel Database Only

The following core features are **completely independent** of WordPress:

1. **Customer Management** (`CustomerModel`)
2. **Booking Management** (`BookingModel`)
3. **Service Management** (`ServiceModel`)
4. **Staff Management** (`StaffModel`)
5. **Package Management** (`PackageModel`)
6. **Event Management** (`EventModel`, `EventInstanceModel`)
7. **Payment Processing** (`PaymentModel`)
8. **Authentication** (Laravel Sanctum)
9. **API Keys** (`ApiKeyModel`)
10. **Admin Panel** (Filament with Laravel models)

### ⚠️ Optional WordPress Integration

WordPress connection is used for:
- **Reading** existing Amelia booking data (if needed)
- **Migration** of Amelia data into Laravel
- **Viewing** Amelia data in admin panel (optional)

**You can disable WordPress connection** by:
1. Removing `WP_DB_*` environment variables from `.env`
2. Removing or commenting out the `wordpress` connection in `config/database.php`
3. The core system will continue to work normally

---

## Verification Checklist

- [x] Default database connection is separate from WordPress
- [x] Laravel models use default connection (not WordPress)
- [x] WordPress models explicitly use `wordpress` connection
- [x] Core features (bookings, customers, services) use Laravel database
- [x] WordPress connection is optional (used only for reading/migration)

---

## Recommendations

### If You Want to Remove WordPress Dependency Completely:

1. **Ensure all data is migrated** from WordPress to Laravel database
2. **Update API endpoints** to use Laravel models instead of Amelia models
3. **Remove WordPress connection** from `config/database.php`
4. **Remove `WP_DB_*` variables** from `.env`
5. **Remove Amelia models** if no longer needed

### If You Want to Keep WordPress Integration:

- Keep the WordPress connection for reading/migration purposes
- Ensure core features use Laravel models (which they already do)
- Consider WordPress connection as "read-only" integration layer

---

## Conclusion

**Your system is correctly architected** with separation between:
- **Laravel database** = Core application data (independent)
- **WordPress database** = Optional integration for reading Amelia data

The core system **does NOT depend on WordPress** and can function independently.
