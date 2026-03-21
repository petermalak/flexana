# Global Firestore customer identity fix (plan)

## Context: data already migrated

Production has **already** run `firestore:migrate` (and related imports). Fixing the root cause therefore requires **two** tracks:

| Track | Purpose |
|--------|--------|
| **A. Reconciliation command (primary for production)** | **Repair existing rows**: merge duplicate `customers`, **move** related data to one canonical customer. This is **not** "create only" — it must **update** `customer_id` on existing `customer_package_purchases`, bookings, payments, device tokens, etc., and **merge** identity fields (`uid`, `firebase_uid`, profile) on the surviving row. |
| **B. Migration command changes** | **Prevent new duplicates** on future `firestore:migrate` / re-imports: normalize phone, resolve customer by uid → normalized phone → email before `Customer::create`. |

**Without track A**, changing `MigrateFromFirestoreCommand` alone does **not** fix users who already have two rows; it only helps **new** or **re-processed** JSON rows.

## Root cause (unchanged)

- Migration matched customers by Firebase `uid` only; phones stored raw.
- App signup uses normalized `+20…` phones → **duplicate `customers`** for the same person.
- Purchases sit on the migrated row; login often uses the signup row → **`/auth/me` empty**.

## A. Reconciliation command (fix existing data)

**Name (example):** `php artisan customers:merge-firestore-duplicates`

**Behavior:**

1. **Dry-run by default** — print what would be merged (counts, duplicate IDs, canonical ID).
2. **`--force`** — apply changes in a **DB transaction**.
3. **Duplicate detection:** group by **normalized phone** (same algorithm as `PhoneVerificationService::normalizePhone()`); optionally also flag groups by **normalized email** where safe.
4. **Canonical row selection (per group):** e.g. prefer row with `uid`/`firebase_uid` set; else row with most `customer_package_purchases`; else oldest `id`. (Document the rule in code.)
5. **For each non-canonical duplicate:**
   - `UPDATE customer_package_purchases SET customer_id = :canonical WHERE customer_id = :duplicate`
   - Same pattern for **other tables** referencing `customer_id` (bookings, payments, `customer_device_tokens`, etc. — enumerate from schema / FKs).
   - Merge **identity** onto canonical: if canonical `uid` is null and duplicate has `uid`, copy; same for `firebase_uid`, `email`, `phone` (normalize to canonical format).
6. **Delete** duplicate row **after** FKs repointed (or soft-delete if you add a flag — default hard delete for true duplicate).
7. **Idempotent** where possible: safe to re-run if no duplicates left.

**Must not:** only insert new customers or new purchases; the value is **rewiring existing** migrated rows.

## B. MigrateFromFirestoreCommand changes (prevent recurrence) — **implemented**

- [`App\Support\PhoneNumberNormalizer`](app/Support/PhoneNumberNormalizer.php) — same rules as `PhoneVerificationService` (used by both).
- **Purchased packages:** resolve customer **uid** → **normalized phone** → **email** (with collision warnings); backfill `uid`/`firebase_uid` on signup-only rows when export has Firebase uid; store **normalized** `phone` on create/update.
- **Payment bookings:** resolve by amelia id → **case-insensitive email** → **normalized phone** → Firebase uid string; new customers get normalized `phone`.

## Documentation

- Update `FIRESTORE_MIGRATION.md` / `MIGRATION_STEPS_ON_SERVER.md`:
  - Deploy code → run **`customers:merge-firestore-duplicates`** (dry-run, then `--force` after backup) → verify `/auth/me`.
  - Optional later: re-run `firestore:migrate` with `--skip-duplicates` if needed for new JSON only.

## Verification

- Staging with production-like duplicate: after merge, single customer has all purchases; `/auth/me` shows sessions.
- Production: backup DB before `--force`.
