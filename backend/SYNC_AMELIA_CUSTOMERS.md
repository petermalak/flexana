# Sync Amelia & Firebase customers

Unify customers from **Amelia** (WordPress) and **Firebase/Firestore** (migrated into Laravel) so one Laravel customer can be used for both Amelia bookings and mobile auth.

---

## Command

```bash
php artisan users:sync-amelia [--dry-run] [--link-firebase]
```

**Requirements:** WordPress/Amelia DB must be reachable (configure `WP_DB_*` in `.env`).

---

## What it does

### 1. Amelia → Laravel (`syncAmeliaToLaravel`)

- Reads all **Amelia users** with `type = 'customer'` from `rueyn_amelia_users`.
- For each Amelia customer:
  - Tries to find a Laravel customer by **amelia_user_id**, **email**, or **phone**.
  - **If found:** sets `amelia_user_id` (if missing) and fills in name/email/phone from Amelia.
  - **If not found:** creates a new Laravel customer with `amelia_user_id`, name, email, phone, `source = 'amelia'`.

Result: every Amelia customer has a corresponding Laravel customer with `amelia_user_id` set.

### 2. Link Firebase-migrated to Amelia (`--link-firebase`)

- Finds Laravel customers that have **uid** or **firebase_uid** (from Firestore migration) but **no amelia_user_id**.
- For each: looks up an Amelia customer by **email** or **phone**.
- If a match is found: sets **amelia_user_id** on the Laravel customer.

Result: users who came from Firebase/Firestore are linked to their Amelia record so the mobile API can book/cancel in Amelia using the same identity.

---

## Options

| Option | Description |
|--------|-------------|
| `--dry-run` | Only report what would be done; no DB writes. |
| `--link-firebase` | Also run step 2 (link Firebase-migrated customers to Amelia by email/phone). |

---

## When to run

- **After Firestore migration:** run with `--link-firebase` so migrated mobile users get `amelia_user_id` and can book in Amelia.
- **After adding customers in Amelia:** run without `--link-firebase` to create/update Laravel customers from Amelia.
- **Ongoing:** run periodically or after bulk imports in Amelia to keep Laravel in sync.

---

## Example

```bash
# Dry run (no changes)
php artisan users:sync-amelia --dry-run --link-firebase

# Sync Amelia → Laravel and link Firebase-migrated customers to Amelia
php artisan users:sync-amelia --link-firebase
```

Summary output: counts of Laravel customers created, updated, linked (Firebase→Amelia), and skipped (already synced).
