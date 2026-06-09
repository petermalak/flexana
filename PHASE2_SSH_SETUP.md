# Phase-2 test on production (SSH only, no cPanel)

Run a second copy of Flexana on the **same server** and **same production database**, at a separate URL, without touching the live app flow.

**Test URL:** `https://admin-panel-flexana-egypt.com/phase2`  
**Live URL:** unchanged (`https://admin-panel-flexana-egypt.com`)

---

## Why not `public_html/public/flexana`?

Uploading the **entire Laravel project** under the web root is unsafe and breaks routing. Only `public/` should be web-accessible. The script below keeps the full app in `flexana-phase2/` (private) and adds a small `/phase2` gateway folder.

```
domains/admin-panel-flexana-egypt/
├── flexana/                 ← live app (do not change)
├── flexana-phase2/          ← test app (new)
└── public_html → flexana/public/
    ├── index.php            ← live
    └── phase2/              ← small gateway only (new)
        ├── index.php        → boots flexana-phase2
        ├── .htaccess
        ├── css/, js/, ...
```

---

## One-command setup (SSH)

```bash
# 1. Upload your phase-2 code to the server (or git pull on the branch with your fixes)
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

# 2. Run the setup script
chmod +x scripts/setup-phase2-ssh.sh
./scripts/setup-phase2-ssh.sh
```

The script will:

1. Copy `flexana/` → `flexana-phase2/` (first run only)
2. Create `.env` from live (same `DB_*`, separate `APP_KEY`, `APP_URL`, cache prefix)
3. Disable real email/SMS and class reminders on phase-2
4. Run `composer install`, `npm run build`, `php artisan optimize`
5. Create `/phase2` gateway under the live web root

---

## Manual setup (if you prefer step by step)

```bash
DOMAIN=/home/flexanastudios/domains/admin-panel-flexana-egypt
cd "$DOMAIN"

# App copy
cp -a flexana flexana-phase2
cd flexana-phase2

# Env: copy live DB settings, change URL and isolation flags
cp ../flexana/.env .env
# Edit .env:
#   APP_URL=https://admin-panel-flexana-egypt.com/phase2
#   LIVEWIRE_BASE_PATH=phase2
#   CACHE_PREFIX=flexana_phase2_cache
#   MAIL_MAILER=log
#   SMS_DRIVER=log
#   SESSIONS_CLASS_REMINDER_ENABLED=false

composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate --force
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan optimize

# Gateway (if public_html is symlink to flexana/public)
GATEWAY="$DOMAIN/flexana/public/phase2"
mkdir -p "$GATEWAY"
# Then run scripts/setup-phase2-ssh.sh — it writes index.php + .htaccess for you
```

---

## Test the package-expiry fix

Point your **test mobile build** (or Postman) at the phase-2 base URL:

| Check | Request |
|-------|---------|
| Session list `willPay` | `GET /phase2/api/v1/sessions` (Bearer token) |
| Package booking allowed | `POST /phase2/api/v1/bookings` with `isDropIn: false`, session **on or before** package expiry |
| Package booking blocked | Same, session **after** package expiry → `400` |

```bash
BASE="https://admin-panel-flexana-egypt.com/phase2"
TOKEN="your-test-customer-token"

curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/v1/sessions" | head -c 500

curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"sessionID":123,"spots":1,"isDropIn":false}' \
  "$BASE/api/v1/bookings"
```

Use a **dedicated test customer** — phase-2 writes to the real database.

---

## What stays safe on live

| Item | Live | Phase-2 |
|------|------|---------|
| URL | `/` | `/phase2` |
| Code folder | `flexana/` | `flexana-phase2/` |
| Mobile app in stores | live URL | only if you change base URL in test build |
| Cron / reminders | keep on live only | do not schedule on phase-2 |
| Emails / SMS | normal | logged only |
| Database | shared | shared |

---

## Remove mistaken nested upload

If you previously uploaded to `public_html/public/flexana`:

```bash
rm -rf /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public/public/flexana
# or, if public_html is a real folder:
# rm -rf /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html/public/flexana
```

Security check (must return 404):

- `https://admin-panel-flexana-egypt.com/.env`
- `https://admin-panel-flexana-egypt.com/phase2/../.env`

---

## Promote to live after testing

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
git pull   # or rsync from flexana-phase2
php artisan optimize:clear && php artisan optimize
```

Live `/phase2` gateway can stay for future tests or be removed:

```bash
rm -rf /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public/phase2
```

---

## Troubleshooting

**404 on `/phase2`**

- Confirm gateway exists: `ls -la flexana/public/phase2/`
- Confirm `mod_rewrite` is on and `.htaccess` has `RewriteBase /phase2/`

**500 error**

- `tail -f flexana-phase2/storage/logs/laravel.log`
- Check `flexana-phase2/.env` DB credentials match live
- `chmod -R 775 flexana-phase2/storage flexana-phase2/bootstrap/cache`

**Admin CSS broken**

- Re-run `npm run build` in `flexana-phase2` and re-run setup script to refresh gateway assets

**Custom subpath** (not `/phase2`):

```bash
GATEWAY_SUBPATH=staging ./scripts/setup-phase2-ssh.sh
```
