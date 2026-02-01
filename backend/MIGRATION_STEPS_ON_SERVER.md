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

## 3. Amelia import (optional)

Only if the server can reach the WordPress/Amelia database and you want to copy Amelia data into Laravel.

**3a. Configure WordPress DB** in `.env`:

```env
WP_DB_HOST=127.0.0.1
WP_DB_PORT=3306
WP_DB_DATABASE=your_wordpress_db
WP_DB_USERNAME=your_user
WP_DB_PASSWORD=your_password
```

**3b. Run the import:**

```bash
cd /var/www/flexana/backend
php artisan amelia:import
```

- First run: no extra options.
- Re-runs: use `--skip-duplicates` to skip existing records.
- Test first: use `--dry-run`.
- Limit scope: e.g. `--only=staff --only=services --only=packages`.

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
| 2 | `php artisan firestore:migrate ...` | Once (or when you have new Firestore exports) |
| 3 | `php artisan amelia:import` | Once, or when you want to refresh from Amelia |
| 4 | `php artisan users:sync-amelia` | Optional, when using Amelia + Laravel customers |

After step 1, run only the steps that apply to your setup (Firestore and/or Amelia).
