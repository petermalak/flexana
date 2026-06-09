# Phase-2 at `https://admin-panel-flexana-egypt.com/phase2`

Test Flexana on the **production database** at a separate URL without changing the live app.

| | URL |
|--|-----|
| **Live** | `https://admin-panel-flexana-egypt.com` |
| **Phase-2 test** | `https://admin-panel-flexana-egypt.com/phase2` |
| **Mobile API** | `https://admin-panel-flexana-egypt.com/phase2/api/v1/...` |

---

## Step 0 — Fix DNS first (required)

`DNS_PROBE_FINISHED_NXDOMAIN` means the domain **does not exist in DNS yet**. No server/Laravel setup will work until this is fixed.

### A. Register the domain (if you do not own it)

Buy `admin-panel-flexana-egypt.com` at your registrar (Namecheap, GoDaddy, etc.).

### B. Point DNS to your server

1. SSH into **server35** (where `/home/flexanastudios/domains/admin-panel-flexana-egypt/` lives):

```bash
ssh flexanastudios@server35
curl -4 ifconfig.me
```

Note the IP (e.g. `203.0.113.10`).

2. At your **domain registrar**, add DNS records:

| Type | Name | Value | TTL |
|------|------|-------|-----|
| A | `@` | `<server IP from above>` | 300 |
| A | `www` | `<same IP>` | 300 |

3. Wait 5–60 minutes, then verify from your PC:

```bash
nslookup admin-panel-flexana-egypt.com
```

You should see the server IP — not "Non-existent domain".

4. Confirm the site responds (live app must be deployed first):

```bash
curl -I https://admin-panel-flexana-egypt.com/up
```

If DNS works but you get 404/500, complete **Step 1** below before phase-2.

> **No cPanel?** Ask your host to attach `admin-panel-flexana-egypt.com` to the existing account folder `domains/admin-panel-flexana-egypt/` and enable SSL. The folder name suggests the account may already exist — only DNS + SSL may be missing.

---

## Step 1 — Live app on the domain (one time)

Server path: `/home/flexanastudios/domains/admin-panel-flexana-egypt/`

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

# Symlink web root (if not already done)
cd ..
rm -rf public_html
ln -s /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public public_html
ls -la public_html

# Live .env must use the domain root (no /phase2)
grep APP_URL .env
# APP_URL=https://admin-panel-flexana-egypt.com

php artisan optimize
```

Test live: **https://admin-panel-flexana-egypt.com/admin**

---

## Step 2 — Phase-2 setup (SSH, no cPanel)

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

chmod +x scripts/setup-phase2-ssh.sh

# Force phase-2 URL even if live .env still points elsewhere
PHASE2_BASE_URL=https://admin-panel-flexana-egypt.com \
PHASE2_APP_URL=https://admin-panel-flexana-egypt.com/phase2 \
./scripts/setup-phase2-ssh.sh
```

This creates:

```
domains/admin-panel-flexana-egypt/
├── flexana/              ← live (unchanged)
├── flexana-phase2/       ← test copy
└── public_html → flexana/public/
    └── phase2/           ← gateway → flexana-phase2
```

Phase-2 `.env` gets:

- `APP_URL=https://admin-panel-flexana-egypt.com/phase2`
- Same `DB_*` as production
- `MAIL_MAILER=log`, `SMS_DRIVER=log` (no real emails/SMS)
- Separate `APP_KEY` and `CACHE_PREFIX`

---

## Step 3 — Verify

```bash
curl -I https://admin-panel-flexana-egypt.com/phase2
curl -I https://admin-panel-flexana-egypt.com/phase2/up
curl -I https://admin-panel-flexana-egypt.com/phase2/api/v1/sessions
```

In the browser:

- `https://admin-panel-flexana-egypt.com/phase2/admin` — Filament admin (phase-2)
- `https://admin-panel-flexana-egypt.com` — live app (unchanged)

Point your **test mobile build** API base to:

`https://admin-panel-flexana-egypt.com/phase2/api/v1`

---

## Test package-expiry fix

```bash
BASE="https://admin-panel-flexana-egypt.com/phase2"
TOKEN="test-customer-bearer-token"

curl -s -H "Authorization: Bearer $TOKEN" "$BASE/api/v1/sessions"

curl -s -X POST -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"sessionID":123,"spots":1,"isDropIn":false}' \
  "$BASE/api/v1/bookings"
```

Use a **test customer only** — bookings write to the real database.

---

## If live is still on another domain (e.g. sdhds.net)

You can run phase-2 on `admin-panel-flexana-egypt.com` while live stays on sdhds.net:

1. Complete **Step 0** (DNS → server35).
2. Deploy code to `flexana/` on server35 (can copy DB credentials from sdhds `.env`).
3. Run Step 2 with `PHASE2_BASE_URL` and `PHASE2_APP_URL` as above.

Live mobile app keeps using sdhds.net until you switch it.

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `DNS_PROBE_FINISHED_NXDOMAIN` | Domain not in DNS — complete Step 0 |
| DNS OK, connection refused | Host has not bound domain to server — contact host |
| 404 on `/phase2` | Run setup script; check `ls flexana/public/phase2/` |
| 500 on `/phase2` | `tail -f flexana-phase2/storage/logs/laravel.log` |
| SSL warning | Enable Let's Encrypt for the domain (host panel or support ticket) |

Remove bad nested upload if present:

```bash
rm -rf /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public/public/flexana
```

---

## After testing — promote to live

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
# deploy phase-2 code into live flexana/
php artisan optimize:clear && php artisan optimize
```
