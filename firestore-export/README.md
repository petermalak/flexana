# Firestore export for Flexana migration

Exports Firestore collections to JSON so you can run `php artisan firestore:migrate`.

## 1. Get your service account key

1. Open [Firebase Console](https://console.firebase.google.com/) → project **flexana-test**
2. Project Settings (gear) → **Service accounts**
3. Click **Generate new private key** → save the JSON file
4. Rename it to `serviceAccountKey.json` and place it in this folder (`backend/firestore-export/`)

**Do not commit** `serviceAccountKey.json` (it is in `.gitignore`).

## 2. Install and run

From this folder:

```bash
cd backend/firestore-export
npm install
npm run export
```

This writes:

- `backend/storage/app/purchased_packages.json`
- `backend/storage/app/payment_bookings.json`

**Purchased packages** are merged from:

- Top-level collections `purchasedPackages` or `purchased_packages` (if any), and  
- **Collection group** queries for each name in `PURCHASE_COLLECTION_GROUPS` inside `export.js` (includes `purchasedPackages`, `purchasedReformPackages`, `purchasePackages`, `unlimitedPackages`, `reformer`, `packages` — matching paths like `users/{uid}/reformer/{packageId}` for `remainingClasses`).

Rows with the same **`uid` + package document `id`** are **merged into one JSON object** (the app often stores purchase metadata and session counts in different subcollections). `_merge_paths` lists Firestore paths combined; `_export_source` lists subcollection names. Dates are normalized so `purchaseDate`/`purchase_date` are filled from `startDate` when needed (unlimited packs).

Add more subcollection names (e.g. yoga-specific) by editing `PURCHASE_COLLECTION_GROUPS` in `export.js`.

If a **collection group** query fails, check the Firebase Console: Firestore may prompt you to create a **composite index**; use the link in the error message.

**Payments:** the script tries `payments`, then `paymentBookings`, then `bookings`. If your Firestore uses different names, edit `export.js`.

## 3. Run the Laravel migration

From the backend root:

```bash
cd backend
php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
```

Use `--dry-run` first to see what would be done without writing.

See **FIRESTORE_MIGRATION.md** in the backend root for full details.
