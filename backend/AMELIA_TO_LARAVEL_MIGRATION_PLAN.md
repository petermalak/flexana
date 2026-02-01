# Amelia to Laravel Data Migration Plan

This document describes the plan to migrate all data read from the Amelia (WordPress) database into the Laravel project, so Laravel becomes the source of truth for the mobile API and eventually for Filament.

## Goals

1. **Schema**: Add Laravel tables/columns that mirror Amelia entities and store Amelia IDs for mapping.
2. **Import**: One-time (or incremental) import of Amelia data into Laravel.
3. **Switch**: Update Mobile API (and optionally Filament) to read from Laravel instead of WordPress.

## Phase 1: Schema (Migrations)

### 1.1 Link Laravel entities to Amelia IDs

| Laravel Table   | New Column         | Purpose                                      |
|-----------------|--------------------|----------------------------------------------|
| `staff`         | `amelia_user_id`   | Map to Amelia `users.id` (type = provider)   |
| `services`      | `amelia_service_id`| Map to Amelia `services.id`                  |
| `packages`      | `amelia_package_id`| Map to Amelia `packages.id`                  |

- `customers` already has `amelia_user_id` (migration `2026_01_31_000005`).

### 1.2 Appointments table (Laravel)

Amelia’s `appointments` table represents bookable slots (service + provider + time). We add a local `appointments` table to hold the same data in Laravel.

| Column               | Type             | Notes                                      |
|----------------------|------------------|--------------------------------------------|
| `id`                 | bigint PK        |                                            |
| `uuid`               | uuid unique      |                                            |
| `amelia_appointment_id` | unsignedBigInteger nullable | For traceability / re-import |
| `service_id`         | FK → services    |                                            |
| `provider_id`        | FK → staff       |                                            |
| `package_id`         | FK → packages nullable |                                    |
| `location_id`        | unsignedBigInteger nullable |                          |
| `booking_start`      | datetime         |                                            |
| `booking_end`        | datetime         |                                            |
| `status`             | string, index    |                                            |
| `internal_notes`     | text nullable    |                                            |
| timestamps           |                  |                                            |

### 1.3 Bookings table updates

| Change                         | Purpose                                                |
|--------------------------------|--------------------------------------------------------|
| `appointment_id` (nullable, FK → appointments) | Link booking to Laravel appointment slot      |
| `amelia_customer_booking_id` (nullable)       | Traceability to Amelia customer_bookings.id   |

Bookings can remain linked to `event_id` / `event_instance_id` for event-based bookings; appointment-based bookings use `appointment_id`.

### 1.4 Package–service pivot

| Laravel Table     | New Column | Purpose (Amelia `packages_to_services.quantity`) |
|-------------------|------------|--------------------------------------------------|
| `package_service` | `quantity` | Sessions per service in package (default 1)      |

## Phase 2: Import Command

**Command**: `php artisan amelia:import`

**Behaviour**:

1. **Staff**: For each Amelia user with `type` in (`provider`, `manager`, `admin`), insert or update `staff` (by `amelia_user_id`), set `amelia_user_id`, name, email, phone, etc. from Amelia `users`.
2. **Services**: For each Amelia `services` row, insert or update Laravel `services` by `amelia_service_id`; map name, duration, price, status, etc.
3. **Packages**: For each Amelia `packages` row, insert or update Laravel `packages` by `amelia_package_id`; map name, price, status, etc.
4. **Service–staff**: From Amelia `providers_to_services`, ensure `service_staff` rows exist (using Laravel `service_id` / `staff_id` resolved via `amelia_service_id` / `amelia_user_id`).
5. **Package–service**: From Amelia `packages_to_services`, ensure `package_service` rows exist with `quantity`; resolve Laravel IDs via `amelia_package_id` / `amelia_service_id`.
6. **Appointments**: For each Amelia `appointments` row, insert or update Laravel `appointments` by `amelia_appointment_id`; set `booking_start`, `booking_end`, `service_id`, `provider_id`, `package_id`, `location_id`, `status`, etc. (resolve FKs via Amelia ID columns).
7. **Customer bookings → Bookings**: For each Amelia `customer_bookings` row, resolve Laravel `customer_id` (by `amelia_user_id`), `appointment_id` (by `amelia_appointment_id`), and optionally create/update Laravel `bookings` with `appointment_id` and `amelia_customer_booking_id`. Handle duplicates (e.g. by `amelia_customer_booking_id`).

**Options**: `--dry-run`, `--skip-duplicates`, `--only=staff` (repeat for multiple: `--only=services --only=packages`, etc.). Supported: `staff`, `services`, `packages`, `service-staff`, `package-service`, `appointments`, `bookings`.

## Phase 3: Switch Mobile API to Laravel

- Add config (e.g. `config/amelia.php` or `.env`): `AMELIA_USE_LARAVEL_DATA=true`.
- In Mobile API controllers (sessions, bookings, instructors, services, packages):
  - If `AMELIA_USE_LARAVEL_DATA=true`: read/write Laravel models (`ServiceModel`, `StaffModel`, `PackageModel`, `AppointmentModel`, `BookingModel`).
  - Else: keep current behaviour (read from Amelia models / WordPress connection).
- Run import before switching; after switch, optionally run import on a schedule until Amelia is deprecated.

## Execution Order

1. Run Phase 1 migrations.
2. Run `php artisan amelia:import` (with `--dry-run` first if desired).
3. Verify data in Laravel (DB/Filament).
4. Enable `AMELIA_USE_LARAVEL_DATA` and test Mobile API.
5. (Optional) Point Filament resources to Laravel models and retire Amelia reads.

## Reference: Amelia Tables → Laravel

| Amelia Table               | Laravel Table / Column(s) |
|----------------------------|---------------------------|
| `users` (provider/manager/admin) | `staff` (+ `amelia_user_id`) |
| `users` (customer)         | `customers` (+ `amelia_user_id`) |
| `services`                  | `services` (+ `amelia_service_id`) |
| `providers_to_services`     | `service_staff`           |
| `packages`                  | `packages` (+ `amelia_package_id`) |
| `packages_to_services`      | `package_service` (+ `quantity`) |
| `appointments`              | `appointments` (+ `amelia_appointment_id`) |
| `customer_bookings`         | `bookings` (+ `appointment_id`, `amelia_customer_booking_id`) |
