# Mobile app authentication (Flexana backend)

This backend is built for a **mobile application**. All auth endpoints under `/api/v1/auth/*` are intended to be called from your iOS or Android app.

---

## Phone signup with Firebase SMS (real SMS to the user)

When a new user signs up with a phone number, the backend asks **Firebase** to send a **real SMS** with a 6-digit code. The app must send **one** verification token so Firebase accepts the request:

| Platform | Send this in the request | How the app gets it |
|----------|---------------------------|----------------------|
| **Android** | `playIntegrityToken` (preferred) or `safetyNetToken` | Play Integrity API or SafetyNet, then pass the token to your backend |
| **iOS** | `iosReceipt` + `iosSecret` | Firebase iOS SDK / App Check flow; get receipt and secret, then send to backend |
| **Web / testing only** | `recaptchaToken` (+ `recaptchaVersion`: `v3`) | reCAPTCHA in a browser (e.g. the included test page) |

### Endpoints the mobile app uses

1. **Request SMS (Firebase sends the real SMS)**  
   - **POST /api/v1/auth/signup**  
   - Body: `phone` + one of: `playIntegrityToken`, `safetyNetToken`, or `iosReceipt` + `iosSecret`.  
   - Optional: `firstName`, `lastName`, `email`.  
   - Response: `sessionInfo` (store it for step 2).

2. **User enters the code from SMS; app verifies and signs in**  
   - **POST /api/v1/auth/verify-with-firebase-code**  
   - Body: `sessionInfo`, `code`, `phone`. Optional: `firstName`, `lastName`, `email`, `password`, `password_confirmation`.  
   - Response: `token` (Bearer), `customer`. Use the token for all later API calls.

Alternative: you can use **POST /api/v1/auth/send-firebase-verification-code** (same body as signup with Firebase token) to request the SMS, then **verify-with-firebase-code** as above.

---

## Backend OTP (optional, no Firebase)

If you don’t use Firebase for SMS, the backend can send its own 6-digit code (via Twilio or log):

- **POST /api/v1/auth/signup** with only `phone` (no Firebase token) → backend sends OTP.
- **POST /api/v1/auth/verify** with `phone` + `code` → returns token.

---

## Test page (web) – for development only

The file **public/test-firebase-sms.html** is a **web page** for developers to test “signup → real SMS → verify” in the browser. It uses **reCAPTCHA** because browsers don’t have Play Integrity or iOS attestation. The **production** client is your mobile app, which should send **playIntegrityToken** (Android) or **iosReceipt** + **iosSecret** (iOS) instead of recaptchaToken.
