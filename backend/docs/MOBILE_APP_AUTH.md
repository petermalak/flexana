# Mobile app authentication (Flexana backend)

This backend is built for a **mobile application**. All auth endpoints under `/api/v1/auth/*` are intended to be called from your iOS or Android app.

---

## Phone signup with backend OTP (Twilio / log)

Firebase SMS integration has been removed. Phone verification is now handled **entirely in the backend**:

- The backend generates a 6-digit code.
- It sends the code via the configured SMS driver (`twilio` in production, `log` in local).
- When the user enters the code, the backend verifies it and flags the account as phone verified.

### Endpoints the mobile app uses

1. **Request SMS (backend sends OTP)**  
   - **POST /api/v1/auth/signup**  
   - Body:
     - `phone` (string, required)
     - Optional: `firstName`, `lastName`, `email`
   - Response:
     - `{ "success": true, "message": "Verification code sent.", "useLegacyVerify": true }`
     - In `local` / `testing` env only, the response may also include the raw `code` for easier manual testing.

2. **User enters the code from SMS; app verifies and signs in**  
   - **POST /api/v1/auth/verify**  
   - Body:
     - `phone` (string, required)
     - `code` (6-digit string, required)
     - Optional: `password`, `password_confirmation` (to set login password at signup)
   - Response:
     - `{ "success": true, "token": "1|...", "customer": { ... } }`
   - On success, the backend sets `phone_verified_at` for that customer.

---

## Test / local behavior

In `local` / `testing` environments and with `SMS_DRIVER=log`, the backend:

- Logs the OTP to `storage/logs/laravel.log`.
- May include the OTP directly in the JSON response from `POST /auth/signup` for easier debugging (never rely on this in production).
