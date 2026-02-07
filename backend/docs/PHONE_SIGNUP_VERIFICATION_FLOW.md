# Phone signup & verification flow

Two options: **Firebase SMS** (SMS sent by Firebase) or **backend OTP** (our 6-digit code via Twilio or log).

---

## Option A: Firebase SMS (recommended for production)

Firebase sends the SMS. You need a **reCAPTCHA token** from the app when requesting the code.

### Flow

1. **Signup with Firebase** – App sends `phone` + `recaptchaToken` (from reCAPTCHA in the app).
2. **Backend** calls Firebase `sendVerificationCode` → Firebase sends the SMS to the user.
3. **Backend** returns `sessionInfo` to the app.
4. **User** receives the SMS and enters the code in the app.
5. **App** sends `sessionInfo` + `code` + `phone` (and optional name, email, password) to **verify-with-firebase-code**.
6. **Backend** exchanges them for a Firebase idToken, then finds/creates the customer and returns a **Sanctum token**.

### API (Firebase SMS)

**Step 1 – Signup (Firebase sends SMS)**

- **POST** `/api/v1/auth/signup`
- **Body:** `{ "phone": "01274235122", "recaptchaToken": "<from app reCAPTCHA>", "firstName": "...", "lastName": "...", "email": "..." }`
- **Response:** `{ "success": true, "message": "Verification code sent.", "sessionInfo": "..." }`  
  Save `sessionInfo` for step 2.

**Step 2 – Verify (user entered code)**

- **POST** `/api/v1/auth/verify-with-firebase-code`
- **Body:** `{ "sessionInfo": "...", "code": "123456", "phone": "01274235122", "password": "...", "password_confirmation": "..." }`
- **Response:** `{ "success": true, "token": "1|...", "customer": { ... } }`

### Firebase setup

- In [Firebase Console](https://console.firebase.google.com) → your project → **Authentication** → **Sign-in method** → enable **Phone**.
- Set in `.env`: `FIREBASE_API_KEY` (Web API Key from Project settings).
- The mobile app must obtain a **reCAPTCHA token** (e.g. Firebase reCAPTCHA verifier or Android SafetyNet / Play Integrity) and send it as `recaptchaToken` in the signup request. Without it, Firebase will reject the send for real phone numbers.
- For **test only**, you can add a test phone number in Firebase (Authentication → Phone → Phone numbers for testing) with a fixed code; then you may not need reCAPTCHA for that number.

---

## Option B: Backend OTP (our code via Twilio or log)

1. **Client signs up with phone** → Backend generates a 6-digit code and sends it (Twilio SMS or log in dev).
2. **User receives the SMS** and enters the code in the app.
3. **App** sends **phone + code** to backend.
4. **Backend** verifies and returns a token.

---

## API steps (Option B – backend OTP)

### Step 1: Signup (request SMS code)

**Request**

- **Method:** `POST`
- **URL:** `/api/v1/auth/signup`
- **Body (JSON):**

```json
{
  "phone": "01274235122",
  "firstName": "Ahmed",
  "lastName": "Ali",
  "email": "ahmed@example.com"
}
```

- **Required:** `phone`
- **Optional:** `firstName`, `lastName`, `email`

**Response (success, 200)**

```json
{
  "success": true,
  "message": "Verification code sent."
}
```

**What the backend does**

- Normalizes the phone number (e.g. `01274235122` → `+201274235122`).
- Creates or updates a customer record with that phone (and optional name/email).
- Generates a 6-digit code, stores it with an expiry (e.g. 10 minutes), and **sends it by SMS** to the phone (via Twilio if configured, otherwise logged in dev).
- Does **not** return a token yet.

---

### Step 2: Verify (send code back and sign in)

**Request**

- **Method:** `POST`
- **URL:** `/api/v1/auth/verify`
- **Body (JSON):**

```json
{
  "phone": "01274235122",
  "code": "123456",
  "password": "MySecret123",
  "password_confirmation": "MySecret123"
}
```

- **Required:** `phone`, `code` (exactly 6 digits)
- **Optional:** `password` + `password_confirmation` (min 8 chars) to set a password for login later

**Response (success, 200)**

```json
{
  "success": true,
  "message": "Verified.",
  "token": "1|abc...",
  "token_type": "Bearer",
  "customer": {
    "id": "1",
    "uid": "...",
    "firstName": "Ahmed",
    "lastName": "Ali",
    "email": "ahmed@example.com",
    "phone": "+201274235122",
    "phoneVerifiedAt": "2026-02-05T12:00:00.000000Z",
    "emailVerifiedAt": null
  }
}
```

**What the backend does**

- Finds a matching verification record for that phone and code (not expired).
- Marks the customer’s phone as verified (`phone_verified_at`).
- If `password` is sent, saves the hashed password so the user can later use **Login** (phone + password).
- Issues a Sanctum API token and returns it with the customer. The app should store the token and use it as `Authorization: Bearer <token>` for all protected endpoints.

---

## Sending real SMS

- **Development:** If no SMS provider is configured, the backend only **logs** the code (e.g. in `storage/logs/laravel.log`). Use that code in the app to test.
- **Production:** Set these in `.env` so the backend sends real SMS via **Twilio**:

```env
SMS_DRIVER=twilio
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+1234567890
```

`TWILIO_FROM` must be a Twilio phone number in E.164 format.

---

## Summary for mobile app

| Step | Endpoint              | App sends                    | App receives / does                    |
|------|------------------------|-----------------------------|----------------------------------------|
| 1    | `POST /auth/signup`    | `phone` (+ optional profile)| “Verification code sent.”              |
| 2    | `POST /auth/verify`    | `phone` + `code` (+ optional password) | `token` + `customer` → store token, user is signed in |

After that, use the token in the `Authorization` header for all other API calls.
