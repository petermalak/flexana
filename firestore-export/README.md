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

The script tries collection names: `purchasedPackages`, then `paymentBookings` or `bookings`, or subcollection `payment/*/booking`. If your Firestore uses different names, edit `export.js` (e.g. change `'paymentBookings'` to your collection name).

## 3. Run the Laravel migration

From the backend root:

```bash
cd backend
php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
```

Use `--dry-run` first to see what would be done without writing.

See **FIRESTORE_MIGRATION.md** in the backend root for full details.
