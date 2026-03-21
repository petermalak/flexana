# Migration steps when deploying on the server

Run these in order when deploying the backend to the server.

---

## 1. Laravel database migrations (required)

Always run after code deploy so the Laravel DB schema is up to date.

```bash
cd /var/www/flexana/backend   # or your app path
php artisan migrate --force
```

- Use `--force` so it runs without confirmation in production.
- If you use `deploy.sh`, it will prompt you to run migrations; answer `y`.

---

## 1b. Package expiry rules (optional, once per environment)

After migrations, sync Amelia/Firestore package duration rules so `/api/v1/auth/me` can compute **`expiresAt`** from **`purchase_date`** (Yoga 3 months, Reformer 12 months, Unlimited 30 days / 3 months by `amelia_package_id`).

```bash
cd /var/www/flexana/backend   # or your app path
php artisan packages:sync-amelia-duration-rules --dry-run   # preview
php artisan packages:sync-amelia-duration-rules
```

- Updates **`packages`** rows where **`amelia_package_id`** is **40–41, 44–46, 47–48** (see `SyncAmeliaPackageDurationRulesCommand`).
- If **0 rows** update, check that **`packages.amelia_package_id`** matches Amelia IDs (not Laravel internal `packages.id`).

---

## 2. Firestore data migration (optional)

Only if you are moving data from Firebase Firestore into Laravel.

**2a. Export from Firestore** (once, e.g. from your machine or a server with Node):

```bash
cd backend/firestore-export
npm install
npm run export
```

This writes `storage/app/purchased_packages.json` and `storage/app/payment_bookings.json`. Copy these to the server if you ran the export elsewhere.

**2b. Run the migration on the server:**

```bash
cd /var/www/flexana/backend
php artisan firestore:migrate \
  --purchased-packages=storage/app/purchased_packages.json \
  --payment-bookings=storage/app/payment_bookings.json
```

- First time: run without `--skip-duplicates`.
- Re-runs: add `--skip-duplicates` to avoid duplicate rows.
- Test first: add `--dry-run` to see what would be done.

---

## 3. Amelia / WordPress data fetch (optional)

Only if the server can reach the WordPress/Amelia database and you want to copy **all** Amelia data into Laravel (locations, customers, staff, services, packages, appointments, bookings, payments, events, categories, tags, resources, settings).

**3a. Configure WordPress DB** in `.env` (use the **WordPress database**, not the Laravel one):

```env
WP_DB_HOST=127.0.0.1
WP_DB_PORT=3306
WP_DB_DATABASE=your_wordpress_database_name
WP_DB_USERNAME=your_user
WP_DB_PASSWORD=your_password
```

- **WP_DB_DATABASE** must be the WordPress/Amelia database name. Do not use the same value as Laravel’s `DB_DATABASE` unless Amelia really lives in that DB.
- **WP_DB_PREFIX** must match your WordPress table prefix + `amelia_`. For example, if Amelia tables are `rueyn_amelia_users`, `rueyn_amelia_services`, etc., set:

```env
WP_DB_PREFIX=rueyn_amelia_
```

**3b. Run the fetch:**

```bash
cd /var/www/flexana/backend
php artisan amelia:fetch
```

- **Staging / production:** Same command; ensure both Laravel and WordPress DB credentials are set in `.env` for that environment.
- First run: no extra options.
- Re-runs: use `--skip-duplicates` to skip existing records.
- Test first: use `--dry-run` to see what would be imported without writing.
- Limit scope: e.g. `php artisan amelia:fetch --only=locations --only=customers --only=appointments`.

**Alternative (legacy):** `php artisan amelia:import` for a subset of entities; prefer `amelia:fetch` for a full sync from the WordPress DB.

---

## 4. Sync Amelia customers (optional)

Only if you use Amelia and want to keep Laravel customers in sync with Amelia users (e.g. link by email/phone).

```bash
cd /var/www/flexana/backend
php artisan users:sync-amelia
```

- To also link Firebase-migrated customers to Amelia:  
  `php artisan users:sync-amelia --link-firebase`
- Test first: `php artisan users:sync-amelia --dry-run`

---

## Summary order on deploy

| Step | Command | When |
|------|---------|------|
| 1 | `php artisan migrate --force` | Every deploy |
| 1b | `php artisan packages:sync-amelia-duration-rules` | Once per env (or after changing package IDs), so `expiresAt` works |
| 2 | `php artisan firestore:migrate ...` | Once (or when you have new Firestore exports) |
| 3 | `php artisan amelia:fetch` | Once, or when you want to refresh from Amelia (WordPress DB) |
| 4 | `php artisan users:sync-amelia` | Optional, when using Amelia + Laravel customers |

After step 1, run only the steps that apply to your setup (Firestore and/or Amelia).
