# Firestore migration guide

This guide explains how to migrate data from Firebase Firestore (project **flexana-test**) into the Laravel backend.

---

## Quick steps

1. **Export Firestore**  
   - Put your Firebase **service account key** as `backend/firestore-export/serviceAccountKey.json`  
   - Run: `cd backend/firestore-export && npm install && npm run export`  
   - This writes `storage/app/purchased_packages.json` and `storage/app/payment_bookings.json`.

2. **Run migration (dry run first)**  
   ```bash
   cd backend
   php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json --dry-run
   ```

3. **Run migration for real**  
   ```bash
   php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
   ```

---

## 1. Export data from Firestore

Use the **in-repo export script** in `backend/firestore-export/`.

### Step 1: Get your service account key

1. Open [Firebase Console](https://console.firebase.google.com/) → project **flexana-test**
2. Click the gear icon → **Project settings** → **Service accounts**
3. Click **Generate new private key** → download the JSON file
4. Rename it to `serviceAccountKey.json` and place it in **`backend/firestore-export/`**

Do not commit this file (it is in `.gitignore`).

### Step 2: Run the export script

From the backend root:

```bash
cd firestore-export
npm install
npm run export
```

This writes JSON into **`backend/storage/app/`**:

- `purchased_packages.json` (from collection `purchasedPackages`)
- `payment_bookings.json` (from `paymentBookings`, or `bookings`, or subcollection `payment/*/booking`)

If your Firestore uses different collection names, edit `firestore-export/export.js` and change the collection names.

### Option: Manual export

If you prefer not to use the script, export your collections to JSON by another method (e.g. a one-off script or Firebase Extensions) and place the files in `backend/storage/app/`. See "Expected JSON format" below.

---

## 2. Expected JSON format

The migration command accepts:

- A **JSON array** of objects, or  
- An object with a **`documents`** key (array of objects), or  
- Firestore-style documents with a **`fields`** object (map of field names to `{ stringValue }, { integerValue }`, etc.).

### purchased_packages.json

Each item should contain (camelCase or snake_case):

| Field              | Type    | Example / note                          |
|--------------------|---------|----------------------------------------|
| uid                | string  | Firebase UID (e.g. `07a8SdsDapd85pEB0I0yDqbH03M2`) |
| email              | string  | User email                             |
| firstName / first_name | string | First name                         |
| lastName / last_name  | string | Last name                          |
| phone              | string  | Phone number                           |
| isVerified         | boolean | If true, sets `phone_verified_at`      |
| packages           | number  | Total sessions in package              |
| remainingClasses / remaining_sessions | number | Remaining sessions |
| purchaseDate / purchase_date / createdAt | string/timestamp | When purchased |

Example (flat array):

```json
[
  {
    "uid": "07a8SdsDapd85pEB0I0yDqbH03M2",
    "email": "sanaraouf14@gmail.com",
    "firstName": "Sana",
    "lastName": "Raouf",
    "phone": "01234511522",
    "isVerified": true,
    "packages": 45,
    "remainingClasses": 5,
    "purchaseDate": "2026-01-20T14:23:32.743808"
  }
]
```

### payment_bookings.json

Each item should contain (camelCase or snake_case):

| Field                | Type   | Example / note                    |
|----------------------|--------|-----------------------------------|
| userId / user_id     | string | User identifier (Amelia id or UID)|
| userEmail / user_email | string | User email                     |
| userName / user_name | string | User full name                 |
| userPhone / user_phone | string | User phone                    |
| bookingDate / booked_at / createdAt | timestamp | Booking time           |
| classPrice / class_price | string/number | Amount (e.g. `"550.0"`)   |
| paymentStatus / payment_status | string | e.g. `completed`        |
| status               | string | e.g. `pending`                    |
| transactionId / transaction_id | string | Unique transaction id        |
| classId, classInstructor, classInstructorId, className, classServiceId, classTime, classDuration | optional | Stored in booking notes / payment meta |
| isTestMode           | boolean | optional, stored in payment meta   |

Example (flat array):

```json
[
  {
    "userId": "31",
    "userEmail": "minamagdypotros92@gmail.com",
    "userName": "Mina Magdy",
    "userPhone": "01207851701",
    "bookingDate": "2026-01-10T14:42:20.000Z",
    "classDate": "2026-01-10T20:30:00.000Z",
    "classId": 53,
    "classInstructor": "Kesha Stovall",
    "classInstructorId": 1568,
    "className": "Reformer Pilates - Ladies only L2",
    "classPrice": "550.0",
    "classServiceId": 53,
    "classTime": "8:30 PM",
    "classDuration": "60 min",
    "paymentStatus": "completed",
    "status": "pending",
    "transactionId": "TEST-1768048940337-53",
    "isTestMode": true
  }
]
```

Timestamps can be:

- ISO 8601 strings (e.g. `"2026-01-10T14:42:20.000Z"`)
- Unix seconds (number)
- Firestore style: `{ "_seconds": 1234567890, "_nanoseconds": 0 }`
- Firestore REST: `{ "timestampValue": "2026-01-10T14:42:20.000Z" }`

---

## 3. Run the migration

1. Place the JSON files in your Laravel project (e.g. `storage/app/purchased_packages.json`, `storage/app/payment_bookings.json`).

2. **Dry run** (no DB changes):
   ```bash
   php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json --dry-run
   ```

3. **Run migration**:
   ```bash
   php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json --payment-bookings=storage/app/payment_bookings.json
   ```

4. **Skip duplicate payments** (by `transaction_id`):
   ```bash
   php artisan firestore:migrate --payment-bookings=storage/app/payment_bookings.json --skip-duplicates
   ```

5. **Only one file**:
   ```bash
   php artisan firestore:migrate --purchased-packages=storage/app/purchased_packages.json
   php artisan firestore:migrate --payment-bookings=storage/app/payment_bookings.json
   ```

---

## 4. What gets created

- **purchasedPackages**  
  - Finds or creates a **customer** by `uid` / `firebase_uid`; sets `phone_verified_at` if `isVerified` is true.  
  - Creates **customer_package_purchases** rows (customer_id, total_sessions, remaining_sessions, purchase_date, status `active`).  
  - `package_id` and `amelia_package_id` are left null (you can map them later if needed).

- **payment_bookings**  
  - Finds or creates a **customer** by `userId` (Amelia id), `userEmail`, `userPhone`, or Firebase `uid`.  
  - Creates a **booking** (event_id null, customer_id, status, payment_status, total_amount from classPrice, booked_at, notes with class/instructor info).  
  - Creates a **payment** (booking_id, transaction_id, amount, status, meta with classId, className, etc.).

---

## 5. After migration

- Run the app and log in with **Laravel auth** (send-code → verify).  
- Existing users should use the same phone/email so they resolve to the migrated customer.  
- You can link Laravel customers to Amelia later by setting `amelia_user_id` where you have a mapping.  
- Remove or archive the Firebase/Firestore collections once you are satisfied with the Laravel data.
