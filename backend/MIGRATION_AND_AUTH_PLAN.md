# Migration & Auth Plan: Firebase/Amelia → Single Backend

This document outlines how to achieve your three goals:

1. **Migrate third-party (Firebase, Amelia) into one backend** – no Firebase/Firestore or Amelia for core app logic.
2. **Migrate old Firestore data** into a well-built structure in this Laravel project.
3. **Add an authentication solution for the mobile app** in this project (no Firebase Auth).

---

## Current State Summary

| Source | Purpose | Data |
|--------|--------|------|
| **Firebase Auth** | Mobile login, SMS verification | Identity only |
| **Firestore** | User profile, purchased packages, payment/booking records | See structures below |
| **Amelia (WordPress)** | Classes, instructors, services, appointments, customer bookings, packages | Used by mobile API today |
| **Laravel DB** | Admin (Filament), some entities | `users`, `customers`, `bookings`, `payments`, `packages`, `services`, `staff`, etc. |

### Firestore Structures (to migrate)

**Collection: purchasedPackages (per user/document)**

| Field | Type | Example |
|-------|------|---------|
| createdAt | timestamp | 19 December 2025 at 15:04:20 UTC+2 |
| email | string | sanaraouf14@gmail.com |
| firstName | string | Sana |
| lastName | string | Raouf |
| phone | string | 01234511522 |
| uid | string | 07a8SdsDapd85pEB0I0yDqbH03M2 (Firebase UID) |
| isVerified | boolean | true |
| packages | number | 45 (e.g. total sessions) |
| remainingClasses | number | 5 |
| purchaseDate | string | 2026-01-20T14:23:32.743808 |

**Collection: payment/booking (per transaction)**

| Field | Type | Example |
|-------|------|---------|
| bookingDate | timestamp | 10 Jan 2026 14:42:20 |
| classDate | timestamp | 10 Jan 2026 20:30:00 |
| classDuration | string | 60 min |
| classId | number | 53 |
| classInstructor | string | Kesha Stovall |
| classInstructorId | number | 1568 |
| className | string | Reformer Pilates - Ladies only L2 |
| classPrice | string | 550.0 |
| classServiceId | number | 53 |
| classTime | string | 8:30 PM |
| createdAt / updatedAt | timestamp | … |
| paymentStatus | string | completed |
| status | string | pending |
| transactionId | string | TEST-1768048940337-53 |
| userEmail | string | minamagdypotros92@gmail.com |
| userId | string | 31 |
| userName | string | Mina Magdy |
| userPhone | string | 01207851701 |
| isTestMode | boolean | true |

---

## Goal 1: Consolidate Third-Party into One Backend

### 1.1 Remove Firebase/Firestore

- **Auth**: Replace Firebase Auth with Laravel-based auth (Sanctum + SMS verification). See Goal 3.
- **Data**: Migrate Firestore documents into Laravel tables (Goal 2). After migration, mobile app reads/writes only to your API.

### 1.2 Amelia (WordPress) Strategy

You have two realistic options:

**Option A – Laravel as source of truth (recommended long-term)**  
- Mobile API reads/writes **only** Laravel DB (`bookings`, `payments`, `packages`, `customers`, `services`, `staff`, etc.).  
- Either: (1) migrate Amelia data into Laravel once and stop using Amelia, or (2) run a one-time sync from Amelia into Laravel, then maintain only Laravel.  
- WordPress/Amelia can stay for legacy or be phased out.

**Option B – Keep Amelia as read source only (short-term)**  
- Mobile API continues to **read** classes/sessions from Amelia (as today).  
- **Writes** (bookings, package purchases, payments) go to **Laravel** only.  
- You add a sync job or dual-write so Amelia and Laravel stay in sync if WordPress UI still needs to show bookings.

**Recommendation:** Aim for Option A. Use the new Laravel schema below; migrate Firestore + Amelia data into it; then point mobile API at Laravel only.

---

## Goal 2: Migrate Firestore Data to a Well-Built Structure

### 2.1 Identity: App Users (Mobile) = Customers

- Use **one** identity for “mobile app user”: the existing **`customers`** table.
- Map Firestore `uid` → `customers.firebase_uid` (keep for migration) and/or add `customers.uid` as a stable app user ID (e.g. UUID you generate).
- Add fields needed for auth and verification (see Goal 3): `password` (nullable), `phone_verified_at`, `email_verified_at`.

So: **no separate “app_users” table**. Mobile users = rows in `customers` with auth fields.

### 2.2 New/Updated Tables

#### A. `customers` table – add columns (migration)

| Column | Type | Purpose |
|--------|------|---------|
| `phone_verified_at` | timestamp nullable | SMS verification (replaces Firestore `isVerified`) |
| `password` | string nullable | Optional password login; null = phone-only |
| `uid` | string nullable, unique, index | Optional stable app user ID (e.g. UUID); Firestore `uid` maps here during migration |

- `firebase_uid`: keep for migration mapping; can be deprecated later.

#### B. `customer_package_purchases` (new) – replaces Firestore “purchasedPackages”

Stores one row per purchase (package bought by a customer).

| Column | Type | Purpose |
|--------|------|---------|
| id | bigint PK | |
| customer_id | FK → customers | Who bought |
| package_id | FK → packages | Which package (Laravel `packages.id` or Amelia package id if you keep mapping) |
| total_sessions | unsigned int | e.g. 45 (from Firestore `packages`) |
| remaining_sessions | unsigned int | e.g. 5 (from Firestore `remainingClasses`) |
| purchase_date | timestamp | From Firestore `purchaseDate` / `createdAt` |
| status | string | e.g. active, expired, cancelled |
| amelia_package_customer_id | bigint nullable | If you still sync with Amelia `packages_customers` |
| created_at, updated_at | timestamps | |

- Firestore: one doc per “purchased package” → one row in `customer_package_purchases`.  
- Map Firestore `uid` → `customers.id` (or `customers.uid`) via migration script.

#### C. Bookings & payments – align with Firestore “payment/booking”

Your existing `bookings` and `payments` tables can hold this; add a few columns or use JSON for class details.

**`bookings`** – ensure you have (or add):

- `customer_id` (already)
- `service_id`, `provider_id` (already from add_service_and_provider migration)
- `booked_at`, `status`, `payment_status` (already)
- Optional: `amelia_appointment_id`, `amelia_customer_booking_id` for mapping to Amelia.
- Optional: `class_duration`, `class_time` (string) if you want them first-class; otherwise store in `meta` JSON.

**`payments`** – ensure you have (or add):

- `booking_id`, `provider`, `provider_reference`, `amount`, `status`, `paid_at` (already)
- `transaction_id` (string, nullable, index) – from Firestore `transactionId`
- `meta` (JSON) – store `classId`, `classInstructorId`, `className`, `classPrice`, `isTestMode`, etc.

Migration: one Firestore payment/booking doc → one `bookings` row + one `payments` row; link by `payments.booking_id = bookings.id`. Map Firestore `userId` to `customers.id` (via `customers.firebase_uid` or `customers.uid` from Firestore `uid`).

### 2.3 Firestore → Laravel migration (implemented)

Use the Artisan command and guide:

- **Command:** `php artisan firestore:migrate --purchased-packages=path/to/purchased_packages.json --payment-bookings=path/to/payment_bookings.json`
- **Guide:** See **`FIRESTORE_MIGRATION.md`** for:
  - How to export Firestore to JSON (Node script with Firebase Admin SDK, or manual)
  - Expected JSON format for purchasedPackages and payment/bookings
  - Options: `--dry-run`, `--skip-duplicates`

The command:

1. **purchasedPackages** → Finds or creates `customers` by `uid`/`firebase_uid`; sets `phone_verified_at` from `isVerified`; creates `customer_package_purchases` (total_sessions, remaining_sessions, purchase_date).
2. **payment/bookings** → Finds or creates `customers` by userId/email/phone; creates `bookings` (event_id null, customer_id, status, payment_status, total_amount, booked_at) and `payments` (transaction_id, amount, meta with classId, etc.).

---

## Goal 3: Mobile Authentication in This Project

### 3.1 Approach: Laravel Sanctum + SMS Verification

- **No Firebase Auth.**  
- **Laravel Sanctum**: issue API tokens for mobile; validate `Authorization: Bearer <token>` on `/api/mobile/*`.  
- **Identity**: same as Goal 2 – **customers** table is the mobile “user”.  
- **Verification**: SMS code (e.g. Twilio, AWS SNS, or another provider) stored and checked in Laravel.

You already have **Laravel Sanctum** and **customers**; you add a **Sanctum guard + provider for customers** and SMS verification flow.

### 3.2 Auth Flow (high level)

1. **Register / Request code**  
   - POST `/api/mobile/auth/send-code`  
   - Body: `{ "phone": "+201234567890" }` (and optionally `email`).  
   - Backend: find or create `customers` by phone; generate 4–6 digit code; store in `phone_verification_codes` (phone, code, expires_at); send SMS via provider.  
   - Response: `{ "message": "Code sent" }` (never return the code).

2. **Verify and “login”**  
   - POST `/api/mobile/auth/verify`  
   - Body: `{ "phone": "...", "code": "123456" }`.  
   - Backend: validate code, update `customers.phone_verified_at`, create Sanctum token for that customer, return token + minimal profile.  
   - Response: `{ "token": "...", "token_type": "Bearer", "customer": { "id", "first_name", "last_name", "email", "phone", "phone_verified_at" } }`.

3. **Authenticated requests**  
   - Header: `Authorization: Bearer <token>`.  
   - Middleware: Sanctum (guard using `customers` provider).  
   - `auth()->user()` = `Customer` model.

4. **Logout**  
   - POST `/api/mobile/auth/logout`  
   - Delete current Sanctum token for the customer.

5. **Optional: profile update**  
   - PUT `/api/mobile/auth/me`  
   - Update name, email (and optionally link to Amelia/later Laravel profile).

### 3.3 Technical Implementation Outline

- **New table: `phone_verification_codes`**  
  - `id`, `phone` (index), `code`, `expires_at`, `created_at`.  
  - Throttle: e.g. 1 code per phone per 60 seconds; expire code after 5–10 minutes.

- **Customer model as Authenticatable for API**  
  - Use Laravel’s `Authenticatable` and `HasApiTokens` (Sanctum) on the **Customer** model (or a base model that `Customer` extends).  
  - Implement `getAuthIdentifierName` / `getAuthIdentifier` (e.g. `id`).

- **Config**  
  - New guard, e.g. `sanctum_mobile`: driver `sanctum`, provider `customers`.  
  - New provider `customers`: driver `eloquent`, model `App\Models\Customer` (or your Customer model).  
  - Use this guard for `/api/mobile/*` routes that require auth.

- **SMS provider**  
  - Install a package or create a simple service (e.g. `App\Application\Auth\SmsVerificationService`) that:  
    - Sends SMS (Twilio/SNS/etc.) from config.  
    - Stores and validates codes in `phone_verification_codes`.

- **Routes**  
  - Public: `POST /api/mobile/auth/send-code`, `POST /api/mobile/auth/verify`.  
  - Protected (Sanctum, guard `sanctum_mobile`): `POST /api/mobile/auth/logout`, `GET/PUT /api/mobile/auth/me`, and all existing mobile booking/package/payment endpoints that should be per-user.

### 3.4 Mobile API Changes After Auth

- **Protected routes**: Attach Sanctum middleware (with customers guard) to:  
  - `POST /api/mobile/bookings`,  
  - `POST /api/mobile/cancel`,  
  - `POST /api/mobile/purchase`,  
  - `GET /api/mobile/sessions` (optional; to fill `isBooked` by current customer).  
- **Current user**: Use `auth()->user()->id` as `customer_id` when creating bookings and package purchases, so you no longer need to pass `customerId`/`customer` in the body (or allow override for admin).  
- **isBooked / canCancel**: In `MobileSessionController`, resolve `customer_id` from `auth()->user()->id` and compute `isBooked` and `canCancel` from `customer_bookings` for that customer.

---

## Suggested Order of Work

1. **Schema**  
   - Add migrations: `customers` (phone_verified_at, password, uid), `phone_verification_codes`, `customer_package_purchases`; add `transaction_id` (and if needed `meta`) to `payments`; add optional Amelia id columns to `bookings` if you want to keep mapping.

2. **Auth**  
   - Implement SMS sending + `phone_verification_codes`; Sanctum on Customer; guards/providers; `send-code` and `verify` endpoints; then protect mobile routes and use `auth()->user()` in booking/purchase/cancel.

3. **Firestore migration**  
   - Script: import Firestore export → customers, customer_package_purchases, bookings, payments. Run and verify.

4. **Amelia**  
   - Decide Option A vs B; if A, migrate or sync Amelia data into Laravel and switch mobile API to Laravel-only reads/writes.

5. **Cleanup**  
   - Remove Firebase SDK from mobile app; remove Firebase Auth and Firestore usage; optionally remove or keep `EnsureFirebaseAuthenticated` / `FirebaseAuthService` for admin if still needed.

---

## Summary Table

| Goal | Outcome |
|------|--------|
| **1. One backend** | Mobile uses only this Laravel API; auth and data live here; Amelia can be read-only then phased out or synced into Laravel. |
| **2. Migrate Firestore** | `customers` + `customer_package_purchases` + `bookings` + `payments` hold all migrated data; migration script maps Firestore docs to these tables. |
| **3. Mobile auth** | Laravel Sanctum + SMS verification; customers table is the mobile user; tokens protect `/api/mobile/*`; no Firebase. |

---

## Implemented (v1 prefix)

- **Prefix:** Mobile API is under `/api/v1/` (see `API_V1_ROUTES.md`).
- **Auth:** Laravel Sanctum + SMS verification; Customer model is the mobile user; guard `api` with provider `customers`.
- **Migrations:** `customers` (uid, phone_verified_at, password, email_verified_at, amelia_user_id), `phone_verification_codes`, `customer_package_purchases`, `payments.transaction_id`.
- **Firebase config:** `config/firebase.php` uses env: `FIREBASE_PROJECT_ID` (default flexana-test), `FIREBASE_API_KEY`, `FIREBASE_STORAGE_BUCKET`, `FIREBASE_GCM_SENDER_ID`. Add these to `.env` for Firestore migration or token verification; do not commit API keys.
