# Reset password flow (mobile API)

Customers reset their password by **email**: request a reset link, receive an email with a token, then submit the token and new password.

---

## 1. Request reset link

**Endpoint:** `POST /api/v1/auth/forgot-password`

**Body (JSON):**
```json
{
  "email": "customer@example.com"
}
```

**Response (always 200, same message for security):**
```json
{
  "success": true,
  "message": "If that email exists, we have sent a password reset link."
}
```

- The backend looks up the customer by **email** (customers table).
- If found, it creates a reset token, stores it in `customer_password_reset_tokens`, and sends an **email** via Laravel’s default password reset notification.
- The email contains a **reset link**. Token expiry is **60 minutes** (config: `config/auth.php` → `passwords.customers.expire`).

**Important for mobile:** The default Laravel email uses a **web URL** (e.g. `https://your-domain.com/reset-password?token=...&email=...`). To support the app:

- **Option A:** Use a **deep link** in that URL (e.g. `yourapp://reset-password?token=...&email=...`) by customising the notification (see below).
- **Option B:** Use a **web page** at that URL that shows the token and instructs the user to open the app and enter it, or that redirects to the app with the token.

---

## 2. Reset password with token

**Endpoint:** `POST /api/v1/auth/reset-password`

**Body (JSON):**
```json
{
  "email": "customer@example.com",
  "token": "the-token-from-email",
  "password": "newSecurePassword123",
  "password_confirmation": "newSecurePassword123"
}
```

**Validation:**
- `email`: required, valid email.
- `token`: required (string from the reset email / link).
- `password`: required, min 8 characters, must match `password_confirmation`.

**Success (200):**
```json
{
  "success": true,
  "message": "Password has been reset."
}
```

**Failure (400):**
```json
{
  "success": false,
  "message": "Invalid or expired reset token."
}
```

- The backend checks the token in `customer_password_reset_tokens` for that email and that it is not expired.
- If valid, it updates the customer’s password and clears the token. The user can then log in with **phone + new password** via `POST /api/v1/auth/login`.

---

## 3. Flow summary

| Step | Who          | Action |
|------|--------------|--------|
| 1    | User / app   | Call `POST /auth/forgot-password` with the account **email**. |
| 2    | Backend      | If email exists, create token, store it, send reset email. |
| 3    | User         | Open email, get the token (from link or from a custom email body). |
| 4    | App          | Show “New password” and “Confirm password” and send `POST /auth/reset-password` with `email`, `token`, `password`, `password_confirmation`. |
| 5    | Backend      | If token valid and not expired, set new password and return success. |
| 6    | User         | Log in with **phone + new password** (`POST /auth/login`). |

---

## 4. Customising the reset email (e.g. deep link for mobile)

By default, the customer receives Laravel’s standard reset notification (web URL). To send a **deep link** or a different message:

1. Create a custom notification, e.g. `App\Notifications\CustomerResetPasswordNotification`.
2. In `App\Models\Customer` (or the Eloquent model that uses `CanResetPassword`), override `sendPasswordResetNotification()` and send that notification instead of the default, passing the token and a URL like `yourapp://reset-password?token=...&email=...`.
3. Ensure the app handles that scheme and opens the “reset password” screen with token and email pre-filled.

If you want, we can add this notification class and override in the customer model next.

---

## 5. Postman

- **Forgot password:** `POST {{base_url}}/api/v1/auth/forgot-password` with body `{ "email": "jane@example.com" }`.
- **Reset password:** `POST {{base_url}}/api/v1/auth/reset-password` with body `{ "email", "token", "password", "password_confirmation" }`.

See the Auth folder in `Flexana_Mobile_API.postman_collection.json` for examples.
