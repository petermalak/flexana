# Update profile with new phone number

Flow to change the logged-in customer’s phone number. The new number must be verified by SMS before it is saved.

---

## 1. Request a verification code (new number)

**Endpoint:** `POST /api/v1/auth/send-phone-change-code`  
**Auth:** Bearer token required  

**Body (JSON):**
```json
{
  "newPhone": "+201234567890"
}
```

- `newPhone`: New phone number. Can be with or without `+` and spaces (e.g. `01234567890` or `+20 123 456 7890`). Backend normalizes to a single format.
- Backend sends a 6-digit OTP to this number (SMS).
- If the number is already used by another account → **400** with message: *"This phone number is already used by another account."*
- Throttle: avoid calling again for the same number within ~60 seconds.

**Success (200):**
```json
{
  "success": true,
  "message": "Verification code sent."
}
```

---

## 2. Confirm phone change (code + new number)

**Endpoint:** `PUT /api/v1/auth/me`  
**Auth:** Bearer token required  

**Body (JSON):**
```json
{
  "phone": "+201234567890",
  "phoneChangeCode": "123456"
}
```

- `phone`: **Same** new number as in step 1 (format can differ, e.g. `01234567890` or `+201234567890` – backend normalizes).
- `phoneChangeCode`: The 6-digit code received by SMS.

**If you send `phone` without `phoneChangeCode`** → **422** with message: *"To change phone, request a code first via POST /auth/send-phone-change-code, then send phone and phoneChangeCode."*

**If code is wrong or expired** → **400** with message: *"Invalid or expired code."*

**If phone is invalid** → **400** with message: *"Invalid phone number."*

**Success (200):** Profile is updated; response includes full customer (with new `phone` and `phone_verified_at`):
```json
{
  "success": true,
  "message": "Profile updated.",
  "customer": {
    "id": "1",
    "phone": "+201234567890",
    "phoneVerifiedAt": "2026-02-15T12:00:00.000000Z",
    ...
  }
}
```

---

## Summary

| Step | Method | Endpoint | Body |
|------|--------|----------|------|
| 1. Send code | POST | `/api/v1/auth/send-phone-change-code` | `{ "newPhone": "+201234567890" }` |
| 2. Confirm | PUT | `/api/v1/auth/me` | `{ "phone": "+201234567890", "phoneChangeCode": "123456" }` |

- Use the **same** new number in both steps (format can differ; backend normalizes).
- Code expires after **10 minutes**.
- Always show the backend `message` on 400/422 so the user knows why the request failed (e.g. wrong code, number already used).

---

## Config (SMS / Twilio)

- **Phone change sends our 6-digit code** so the same code is stored in the DB and sent by SMS. If only **Twilio Verify** is configured (no Twilio “from” number), the backend used to send Verify’s own OTP and the user received a different (or fixed test) code, so verification always failed.
- **Current behaviour:** When the backend has a code to send (e.g. phone change), it prefers sending that code via **Twilio SMS** (requires `sms.twilio.from`). If only Twilio Verify is configured, it still sends via Verify; in that case verification can succeed by checking the code with **Twilio Verify API** (fallback in the backend).
- For phone change to work with **our** code (new code each time), configure a Twilio “from” number so the app can send SMS with the generated code.
