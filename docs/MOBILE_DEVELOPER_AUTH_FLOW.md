# Mobile auth flow

Single flow for the Flexana mobile app. Base URL: `https://your-api.com/api/v1`.

---

## Flow (signup → SMS → verify → signed in)

```
Signup (new user):
  [User enters phone] → [App gets attestation token] → POST /auth/signup
       → Backend → Firebase sends SMS to phone
       → App gets sessionInfo → [Show "Enter code" screen]
  [User enters code (+ optional password)] → POST /auth/verify-with-firebase-code
       → Backend returns token + customer
  → App stores token → user signed in
```

**Where the user adds password:** On the **“Enter code”** screen (or right after), the app can ask for **password** and **confirm password**. Send them in the same request as the code: **POST /auth/verify-with-firebase-code** with `password` and `password_confirmation`. If you send them, the user can later use **Login** with phone + password.

---

## 1. Login (existing user)

- **Endpoint:** `POST /api/v1/auth/login`
- **Body:** `{ "phone": "+201274235122", "password": "..." }`
- **Response:** `{ "success": true, "token": "1|...", "customer": { ... } }`
- **App:** Store `token`, use `Authorization: Bearer <token>` for all other requests.

---

## 2. Signup (new user) – two steps

### Step 1 – Request SMS

- **Endpoint:** `POST /api/v1/auth/signup`
- **Body:** `phone` + **one** attestation token:
  - **Android:** `playIntegrityToken` or `safetyNetToken`
  - **iOS:** `iosReceipt` + `iosSecret`
- Optional: `firstName`, `lastName`, `email`

Example (Android):
```json
{
  "phone": "+201274235122",
  "playIntegrityToken": "<from Play Integrity API>",
  "firstName": "Ahmed",
  "lastName": "Ali",
  "email": "ahmed@example.com"
}
```

- **Response:** `{ "success": true, "message": "Verification code sent.", "sessionInfo": "..." }`
- **App:** Save `sessionInfo`. Show “Enter the 6-digit code from SMS”. Optionally show password + confirm password fields (for setting password now).

### Step 2 – Enter code (and optional password) → verify and sign in

- **Endpoint:** `POST /api/v1/auth/verify-with-firebase-code`
- **Body (required):** `sessionInfo`, `code`, `phone`
- **Body (optional):** `firstName`, `lastName`, `email`, `password`, `password_confirmation`

Example (with password):
```json
{
  "sessionInfo": "<from step 1>",
  "code": "123456",
  "phone": "+201274235122",
  "firstName": "Ahmed",
  "lastName": "Ali",
  "email": "ahmed@example.com",
  "password": "secret123",
  "password_confirmation": "secret123"
}
```

- **Response:** `{ "success": true, "token": "1|...", "customer": { ... } }`
- **App:** Store `token`. User is signed in. Use `Authorization: Bearer <token>` for all other requests.

---

## 3. After login or signup

- Use header **`Authorization: Bearer <token>`** on every request to protected endpoints (me, sessions, book, appointments history, packages, etc.).
- **Logout:** `POST /api/v1/auth/logout` (with Bearer).

---

## 4. Auth APIs (only what you need)

| Purpose | Method | Endpoint | Body |
|--------|--------|----------|------|
| Login | POST | `/api/v1/auth/login` | `phone`, `password` |
| Signup step 1 (request SMS) | POST | `/api/v1/auth/signup` | `phone`, one of `playIntegrityToken` / `safetyNetToken` / `iosReceipt`+`iosSecret`, optional `firstName`, `lastName`, `email` |
| Signup step 2 (verify + sign in) | POST | `/api/v1/auth/verify-with-firebase-code` | `sessionInfo`, `code`, `phone`, optional `firstName`, `lastName`, `email`, `password`, `password_confirmation` |
| Get my profile | GET | `/api/v1/auth/me` | — (Bearer) |
| Update profile | PUT | `/api/v1/auth/me` | `firstName?`, `lastName?`, `email?` or `phone`+`phoneChangeCode` for phone change |
| Send phone change code | POST | `/api/v1/auth/send-phone-change-code` | `newPhone` (Bearer) |
| Change password | POST | `/api/v1/auth/change-password` | `currentPassword`, `password`, `password_confirmation` (Bearer) |
| Delete account | DELETE | `/api/v1/auth/delete-account` | — (Bearer) |
| Logout | POST | `/api/v1/auth/logout` | — (Bearer) |
| Forgot password | POST | `/api/v1/auth/forgot-password` | `email` |
| Reset password | POST | `/api/v1/auth/reset-password` | `email`, `token`, `password`, `password_confirmation` |

---

## 5. Summary

1. **Login:** `POST /auth/login` with phone + password → store token.
2. **Signup:**  
   - `POST /auth/signup` with phone + attestation token → get `sessionInfo`.  
   - User enters code from SMS (and optionally password).  
   - `POST /auth/verify-with-firebase-code` with `sessionInfo`, `code`, `phone` (and optional password) → store token.
3. Use **`Authorization: Bearer <token>`** for all other requests.

Password is set in **step 2** when the user enters the code: add password fields on that screen and send `password` and `password_confirmation` in **verify-with-firebase-code**.

---

## 6. Troubleshooting: Signup returns 400 / "Internal error"

If **POST /auth/signup** with `playIntegrityToken` returns **400** with *"Internal error encountered"* or *"Verification service error"*, Firebase is rejecting the token. Fix it on the **Firebase and Android** side:

- **See [FIREBASE_PHONE_ANDROID_SETUP.md](FIREBASE_PHONE_ANDROID_SETUP.md)** for step-by-step: add your app’s **SHA-256** in Firebase Console, enable Phone auth, use the same API key, and enable Play Integrity API in Google Cloud.
- Backend logs the exact Firebase error: check `storage/logs/laravel.log` for `Firebase sendVerificationCode failed` and `firebase_error_code` (e.g. 18002 = app not recognized; add the correct SHA-256 for your build).
