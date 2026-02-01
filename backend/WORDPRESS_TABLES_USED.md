# WordPress/Amelia Tables Used by Laravel Project

This document lists all the WordPress/Amelia database tables that the Laravel project accesses.

## Table Prefix

All Amelia tables use the prefix: **`rueyn_amelia_`**

This prefix is configured in `config/database.php` and is automatically applied to all queries using the `wordpress` connection.

## Tables Used

### 1. Core Booking Tables

#### `rueyn_amelia_appointments`
- **Model**: `AmeliaAppointmentModel`
- **Usage**: Stores appointment/session information
- **Key Fields**: `id`, `status`, `bookingStart`, `bookingEnd`, `serviceId`, `providerId`, `packageId`
- **Used By**: 
  - Mobile API (sessions, bookings)
  - Filament Resources (AmeliaAppointmentResource)

#### `rueyn_amelia_customer_bookings`
- **Model**: `AmeliaCustomerBookingModel`
- **Usage**: Stores customer bookings linked to appointments
- **Key Fields**: `id`, `appointmentId`, `customerId`, `status`, `price`, `persons`, `created`
- **Used By**: 
  - Mobile API (bookings, cancellations)
  - Filament Resources (AmeliaBookingResource)

### 2. User & Provider Tables

#### `rueyn_amelia_users`
- **Model**: `AmeliaUserModel`
- **Usage**: Stores customers, providers (instructors), and staff
- **Key Fields**: `id`, `firstName`, `lastName`, `email`, `phone`, `type`, `status`, `picture`, `description`
- **Used By**: 
  - Mobile API (instructors, customers)
  - Filament Resources (AmeliaCustomerResource, AmeliaEmployeeResource)

### 3. Service Tables

#### `rueyn_amelia_services`
- **Model**: `AmeliaServiceModel`
- **Usage**: Stores service/class information
- **Key Fields**: `id`, `name`, `description`, `price`, `status`, `minCapacity`, `maxCapacity`, `duration`, `settings`
- **Used By**: 
  - Mobile API (services, sessions)
  - Filament Resources (AmeliaServiceResource)

#### `rueyn_amelia_providers_to_services`
- **Model**: `AmeliaProviderServiceModel`
- **Usage**: Junction table linking providers to services
- **Key Fields**: `id`, `userId`, `serviceId`, `price`, `minCapacity`, `maxCapacity`
- **Used By**: 
  - Filament Resources (AmeliaProviderServiceResource)

### 4. Package Tables

#### `rueyn_amelia_packages`
- **Model**: `AmeliaPackageModel`
- **Usage**: Stores package information
- **Key Fields**: `id`, `name`, `description`, `price`, `status`, `durationType`, `durationCount`, `settings`
- **Used By**: 
  - Mobile API (packages, purchases)
  - Filament Resources (AmeliaPackageResource)

#### `rueyn_amelia_packages_to_services`
- **Model**: `AmeliaPackageServiceModel`
- **Usage**: Junction table linking packages to services
- **Key Fields**: `id`, `packageId`, `serviceId`, `quantity`
- **Used By**: 
  - Mobile API (counting sessions in packages)
  - Filament Resources (AmeliaPackageServiceResource)
- **Direct Query**: Used in `MobilePackageController` to count sessions

#### `rueyn_amelia_packages_customers`
- **Usage**: Stores package purchases by customers
- **Key Fields**: `id`, `packageId`, `customerId`, `price`, `purchased`, `status`
- **Used By**: 
  - Mobile API (package purchases)
- **Direct Query**: Used in `MobilePackageController` (no model yet)

### 5. Payment Tables

#### `rueyn_amelia_payments`
- **Model**: `AmeliaPaymentModel`
- **Usage**: Stores payment information
- **Key Fields**: `id`, `customerBookingId`, `amount`, `dateTime`, `status`, `gateway`, `transactionId`
- **Used By**: 
  - Filament Resources (for displaying payment data)

### 6. Event Tables

#### `rueyn_amelia_events`
- **Model**: `AmeliaEventModel`
- **Usage**: Stores event information
- **Key Fields**: `id`, `name`, `status`, `maxCapacity`, `price`, `bookingOpens`, `bookingCloses`, `settings`
- **Used By**: 
  - Filament Resources (AmeliaEventResource)

#### `rueyn_amelia_events_periods`
- **Model**: `AmeliaEventPeriodModel`
- **Usage**: Stores event time periods
- **Key Fields**: `id`, `eventId`, `periodStart`, `periodEnd`
- **Used By**: 
  - Filament Resources (AmeliaEventPeriodResource)

#### `rueyn_amelia_events_to_tickets`
- **Model**: `AmeliaEventTicketModel`
- **Usage**: Stores event tickets
- **Key Fields**: `id`, `eventId`, `enabled`, `name`, `price`, `spots`, `dateRanges`
- **Used By**: 
  - Filament Resources (AmeliaEventTicketResource)

## Summary Table

| Table Name (with prefix) | Model | Primary Use | Mobile API | Filament |
|--------------------------|-------|------------|-----------|----------|
| `rueyn_amelia_appointments` | ✅ | Sessions/Appointments | ✅ | ✅ |
| `rueyn_amelia_customer_bookings` | ✅ | Customer Bookings | ✅ | ✅ |
| `rueyn_amelia_users` | ✅ | Customers/Instructors | ✅ | ✅ |
| `rueyn_amelia_services` | ✅ | Services/Classes | ✅ | ✅ |
| `rueyn_amelia_providers_to_services` | ✅ | Provider-Service Links | ❌ | ✅ |
| `rueyn_amelia_packages` | ✅ | Packages | ✅ | ✅ |
| `rueyn_amelia_packages_to_services` | ✅ | Package-Service Links | ✅ | ✅ |
| `rueyn_amelia_packages_customers` | ❌ | Package Purchases | ✅ | ❌ |
| `rueyn_amelia_payments` | ✅ | Payments | ❌ | ✅ |
| `rueyn_amelia_events` | ✅ | Events | ❌ | ✅ |
| `rueyn_amelia_events_periods` | ✅ | Event Periods | ❌ | ✅ |
| `rueyn_amelia_events_to_tickets` | ✅ | Event Tickets | ❌ | ✅ |

## Database Connection

All these tables are accessed through the `wordpress` database connection configured in `config/database.php`:

```php
'wordpress' => [
    'driver' => 'mysql',
    'host' => env('WP_DB_HOST', '127.0.0.1'),
    'port' => env('WP_DB_PORT', '3306'),
    'database' => env('WP_DB_DATABASE', 'cyanboutique_flexana'),
    'username' => env('WP_DB_USERNAME', 'root'),
    'password' => env('WP_DB_PASSWORD', ''),
    'prefix' => 'rueyn_amelia_',
    // ...
]
```

## Verification Query

To verify all tables exist in your database, run:

```sql
SHOW TABLES LIKE 'rueyn_amelia_%';
```

Expected output should include all 12 tables listed above.

## Notes

1. **Table Prefix**: The prefix `rueyn_amelia_` is automatically added by Laravel when using the `wordpress` connection, so models define table names without the prefix.

2. **Direct Queries**: Some controllers use direct DB queries with the full table name (e.g., `rueyn_amelia_packages_services`), but these should ideally use models for consistency.

3. **Missing Model**: `rueyn_amelia_packages_customers` is accessed via direct query in `MobilePackageController` but doesn't have a model yet. Consider creating `AmeliaPackageCustomerModel` for consistency.

4. **Read vs Write**: Most operations are read-only. Write operations (create/update/delete) are controlled by the `AMELIA_ENABLE_WRITE` environment variable.
