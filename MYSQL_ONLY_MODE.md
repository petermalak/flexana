# MySQL-Only Mode (WordPress Connection Off)

WordPress database connection has been **turned off**. All data is now read from **MySQL** (your default database, e.g. `flexana`).

## Mobile API – Already MySQL-Only

**Yes. The switch is applied for the Mobile API as well.** The Mobile API has always used Laravel/MySQL models only; it does not use WordPress or Amelia models.

| Endpoint / Controller           | Data source (MySQL)        |
|---------------------------------|----------------------------|
| Sessions (appointments)         | `AppointmentModel`, `ServiceModel`, `StaffModel`, `BookingModel` |
| Bookings (history, create, cancel) | `BookingModel`, `AppointmentModel`, `Customer`, `PaymentModel` |
| Services                        | `ServiceModel`             |
| Instructors                     | `StaffModel`               |
| Packages (list, purchase)       | `PackageModel`, `CustomerPackagePurchaseModel`, `PaymentModel` |
| Auth                            | Laravel `Customer` (customers table) |

No Mobile controller uses `AmeliaCustomerResolver`, Amelia models, or the WordPress connection. So the Mobile API is fully on MySQL.

---

## What Changed (Admin / Backend)

### 1. Config
- **`config/database.php`** – WordPress connection removed. Only the default MySQL connection is used.

### 2. Filament Admin (Amelia Data)
These resources now use **Laravel MySQL models** instead of WordPress/Amelia:

| Admin Section        | Before (WordPress)     | After (MySQL)     |
|----------------------|------------------------|-------------------|
| Amelia Bookings      | AmeliaCustomerBookingModel | BookingModel      |
| Amelia Customers     | AmeliaUserModel        | CustomerModel     |
| Amelia Services      | AmeliaServiceModel     | ServiceModel      |
| Amelia Packages      | AmeliaPackageModel     | PackageModel      |
| Amelia Employees     | AmeliaUserModel        | StaffModel        |
| Amelia Appointments  | AmeliaAppointmentModel | AppointmentModel  |

Column names in forms/tables use **snake_case** (e.g. `first_name`, `booked_at`, `total_amount`).

### 3. Stats Widget
- **AmeliaStatsOverview** – Uses `BookingModel`, `AppointmentModel`, `CustomerModel`, `PaymentModel` (all MySQL).

### 4. Auth
- **AmeliaCustomerResolver** – No longer uses WordPress. `resolveAmeliaUser()` now returns the Laravel `Customer` (MySQL) as the single source of truth.

### 5. Sync/Import Commands
- **`amelia:sync-to-mysql`** – Exits with a message when WordPress is disabled (no sync needed).
- **`amelia:import`** – Same.
- **`users:sync-amelia`** – Same.

## Database You Use

- **Single database:** MySQL (default connection).
- **`.env`:** Use `DB_*` only (e.g. `DB_CONNECTION=mysql`, `DB_DATABASE=flexana`).
- **`WP_DB_*`** – Not used; can be removed from `.env` if you want.

## Resources That Still Use Amelia Models

These Filament resources still reference Amelia models and will **error** if opened (connection `wordpress` no longer exists):

- Amelia Events
- Amelia Event Periods  
- Amelia Event Tickets
- Amelia Package Services
- Amelia Provider Services

Use the main **Scheduling** / **Events** resources (e.g. **EventResource**, **EventInstanceResource**) for events and related data in MySQL. Package/service links are managed through the main Package and Service resources.

## Re-enabling WordPress (Optional)

If you need the WordPress connection again later:

1. In `config/database.php`, add back the `wordpress` entry under `connections` (see git history or backup).
2. Set `WP_DB_*` in `.env`.
3. Sync/import commands will work again when the WordPress DB is reachable.
