# WordPress Amelia → Laravel MySQL tab/entity mapping

This document maps each **WordPress Amelia** admin “tab” / entity to the corresponding **Laravel (MySQL)** table(s) in the Flexana project. Use it to confirm that every Amelia entity has an alternative in your database.

---

## Summary: Amelia entity → Laravel table(s)

| # | Amelia (WordPress) entity | Amelia DB table (if used in code) | Laravel MySQL table(s) | Status |
|---|---------------------------|-----------------------------------|--------------------------|--------|
| 1 | **Services** | `services` | `services` (+ `amelia_service_id`) | ✅ Yes |
| 2 | **Employees** (Providers / Staff) | `users` (type = provider/manager) | `staff` (+ `amelia_user_id`) | ✅ Yes |
| 3 | **Customers** | `users` (type = customer) | `customers` (+ `amelia_user_id`) | ✅ Yes |
| 4 | **Appointments** | `appointments` | `appointments` (+ `amelia_appointment_id`) | ✅ Yes |
| 5 | **Customer Bookings** | `customer_bookings` | `bookings` (+ `appointment_id`, `amelia_customer_booking_id`) | ✅ Yes |
| 6 | **Packages** | `packages` | `packages` (+ `amelia_package_id`) | ✅ Yes |
| 7 | **Package ↔ Service** (packages to services) | `packages_to_services` | `package_service` (+ `quantity`) | ✅ Yes |
| 8 | **Provider ↔ Service** (employee–service link) | `providers_to_services` | `service_staff` | ✅ Yes |
| 9 | **Events** | `events` | `events` | ✅ Yes |
| 10 | **Event Periods** | `events_periods` | `event_periods` | ✅ Yes |
| 11 | **Event Tickets** | `events_to_tickets` | `event_tickets` | ✅ Yes |
| 12 | **Payments** | `payments` | `payments` (+ `promo_code_id`, etc.) | ✅ Yes |
| 13 | **Locations** | (Amelia locations) | `locations` (+ FK on event_instances, appointments) | ✅ Yes |
| 14 | **Categories** (service categories) | (Amelia categories) | `categories` (+ `services.category_id` string) | ✅ Yes |
| 15 | **Coupons** | (Amelia coupons) | `promo_codes` | ✅ Yes (as promo codes) |
| 16 | **Tags** | (Amelia tags) | `tags` + `taggables` (polymorphic) | ✅ Yes |
| 17 | **Settings** | (Amelia settings) | `settings` (key/value) | ✅ Yes |
| 18 | **Resources** | (Amelia resources) | `resources` (bookable resources) | ✅ Yes |
| 19 | **Custom Fields** | (Amelia custom fields) | `custom_fields` (definitions) + `bookings.answers` (values) | ✅ Yes |

---

## Detailed mapping

### 1. Services  
- **Amelia:** Services tab → `services` table.  
- **Laravel:** `services` table; link via `amelia_service_id`.  
- **Admin:** ServiceResource (and AmeliaServiceResource if enabled).

### 2. Employees (Providers / Staff)  
- **Amelia:** Employees tab → `users` with type provider/manager/admin.  
- **Laravel:** `staff` table; link via `amelia_user_id`.  
- **Admin:** StaffResource (and AmeliaEmployeeResource if enabled).

### 3. Customers  
- **Amelia:** Customers tab → `users` with type customer.  
- **Laravel:** `customers` table; link via `amelia_user_id`.  
- **Admin:** CustomerResource (and AmeliaCustomerResource if enabled).

### 4. Appointments  
- **Amelia:** Appointments tab → `appointments` (slot: service + provider + time).  
- **Laravel:** `appointments` table; link via `amelia_appointment_id`.  
- **Admin:** AmeliaAppointmentResource (hidden); mobile API uses `appointments` for “sessions”.

### 5. Customer Bookings  
- **Amelia:** Bookings per customer → `customer_bookings`.  
- **Laravel:** `bookings` table; `appointment_id` for slot, `amelia_customer_booking_id` for traceability. Also supports event-based bookings via `event_id` / `event_instance_id`.  
- **Admin:** BookingResource.

### 6. Packages  
- **Amelia:** Packages tab → `packages`.  
- **Laravel:** `packages` table; link via `amelia_package_id`.  
- **Admin:** PackageResource (and AmeliaPackageResource if enabled).

### 7. Package ↔ Service (which services in a package)  
- **Amelia:** `packages_to_services` (with quantity).  
- **Laravel:** `package_service` pivot (package_id, service_id, quantity).  
- **Admin:** Managed via Package resource / AmeliaPackageServiceResource.

### 8. Provider ↔ Service (which employees do which services)  
- **Amelia:** `providers_to_services`.  
- **Laravel:** `service_staff` pivot (service_id, staff_id).  
- **Admin:** Via Service/Staff or AmeliaProviderServiceResource.

### 9. Events  
- **Amelia:** Events tab → `events`.  
- **Laravel:** `events` table; `event_instances` for occurrences.  
- **Admin:** EventResource, EventInstanceResource (and Amelia Events if enabled).

### 10. Event Periods  
- **Amelia:** `events_periods` (recurring/periods for events).  
- **Laravel:** No dedicated `event_periods` or `events_periods` table. Logic can live in `events` / `event_instances` or be added later.  
- **Gap:** Consider a migration to add `event_periods` (or similar) if you need full parity.

### 11. Event Tickets  
- **Amelia:** `events_to_tickets` (ticket types for events).  
- **Laravel:** No dedicated `event_tickets` or `events_to_tickets` table.  
- **Gap:** Add an `event_tickets` (or `events_to_tickets`) table if you need ticket types and pricing per event.

### 12. Payments  
- **Amelia:** Payments → `payments`.  
- **Laravel:** `payments` table (linked to bookings, optional `promo_code_id`).  
- **Admin:** PaymentResource.

### 13. Locations  
- **Amelia:** Locations tab.  
- **Laravel:** No `locations` table. Only `event_instances.location_id` and `appointments.location_id` as nullable IDs (no FK to a locations table).  
- **Gap:** Add a `locations` table and FKs if you need to manage locations as first-class entities.

### 14. Categories (e.g. service categories)  
- **Amelia:** Categories for services.  
- **Laravel:** `services.category_id` (string, e.g. "Yoga", "Reformer Pilates"); no separate categories table.  
- **Note:** Category is stored inline; add a `categories` table if you want normalized categories.

### 15. Coupons  
- **Amelia:** Coupons.  
- **Laravel:** `promo_codes` (code, percent_discount, validity, usage_limit).  
- **Admin:** PromoCodeResource.

### 16. Tags  
- **Amelia:** Tags (e.g. for services/events).  
- **Laravel:** No tags table or taggables.  
- **Gap:** Add tags (e.g. `tags` + polymorphic pivot) if required.

### 17. Settings  
- **Amelia:** Plugin settings.  
- **Laravel:** Handled via `config/`, `.env`, or a `settings` table if you add one. Not a direct Amelia “tab” table.

### 18. Resources (Amelia “Resources”)  
- **Amelia:** Bookable resources.  
- **Laravel:** No dedicated resources table.  
- **Gap:** Add a `resources` table and relations if you use Amelia resources.

### 19. Custom Fields  
- **Amelia:** Custom fields on bookings.  
- **Laravel:** `bookings.answers` (JSON) and optional `customers`/other JSON columns.  
- **Note:** Partial; no separate custom_field definitions table unless you add one.

---

## Laravel-only tables (no Amelia “tab” equivalent)

These exist in your MySQL schema for the app and mobile API; they are not direct copies of an Amelia tab:

- `users` – Laravel/Filament admin users (not Amelia “users”).
- `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` – framework.
- `password_reset_tokens`, `personal_access_tokens` – auth.
- `phone_verification_codes`, `customer_password_reset_tokens`, `customer_device_tokens` – mobile auth/FCM.
- `customer_package_purchases` – package purchases (remaining sessions, etc.).
- `api_keys`, `banners`, `class_types`, `staff_schedules`, `event_service` – app-specific.
- Permission tables (Spatie) – admin roles/permissions.

---

## Quick checklist: “Does each Amelia tab have an alternative?”

| Amelia tab / entity   | Alternative in MySQL? | Laravel table(s) |
|-----------------------|------------------------|-------------------|
| Services              | ✅ Yes                 | `services` |
| Employees             | ✅ Yes                 | `staff` |
| Customers             | ✅ Yes                 | `customers` |
| Appointments          | ✅ Yes                 | `appointments` |
| Customer Bookings     | ✅ Yes                 | `bookings` |
| Packages              | ✅ Yes                 | `packages` |
| Package–Service link  | ✅ Yes                 | `package_service` |
| Provider–Service link | ✅ Yes                 | `service_staff` |
| Events                | ✅ Yes                 | `events`, `event_instances` |
| Event Periods         | ⚠️ No                  | — (consider adding) |
| Event Tickets         | ⚠️ No                  | — (consider adding) |
| Payments              | ✅ Yes                 | `payments` |
| Locations             | ⚠️ No                  | Only FK columns (consider `locations` table) |
| Categories            | ⚠️ Inline              | `services.category_id` (no categories table) |
| Coupons               | ✅ Yes                 | `promo_codes` |
| Tags                  | ❌ No                   | — |
| Settings              | N/A                    | config/env |
| Resources             | ❌ No                   | — |
| Custom Fields         | ⚠️ Partial             | `bookings.answers` (JSON) |

---

## Recommended next steps

1. **Event Periods:** If you use Amelia event periods, add a migration for `event_periods` (or `events_periods`) and sync from Amelia.  
2. **Event Tickets:** If you use ticket types per event, add `event_tickets` (or `events_to_tickets`) and link to events.  
3. **Locations:** If you manage locations in Amelia, add a `locations` table and point `event_instances.location_id` and `appointments.location_id` to it.  
4. **Tags / Resources:** Add only if your product needs them; then add the corresponding tables and Filament resources.

Use this file as the single place to check that each WordPress Amelia tab has an alternative in your MySQL database and to track gaps (e.g. event periods, event tickets, locations, tags, resources).
