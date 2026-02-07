# Test the full phone signup cycle

Two ways to test: **Artisan command** (no server) or **HTTP** (with server running).

---

## Option 1: Artisan command (no server needed)

Runs signup → verify → token in one go. The code is returned by the service so you see it in the output.

```bash
cd backend
php artisan auth:test-phone-signup 01274235122 --name="Test User"
```

Optional: set a password so the user can later log in with phone + password:

```bash
php artisan auth:test-phone-signup 01274235122 --name="Test" --password=Secret123
```

**Output:** You’ll see the 6-digit code and the Bearer token. Use the token for authenticated requests.

---

## Option 2: HTTP (server running)

Start the server in one terminal:

```bash
cd backend
php artisan serve
```

In another terminal (or Postman), run the two requests.

### Step 1: Signup

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/signup \
  -H "Content-Type: application/json" \
  -d "{\"phone\": \"01274235122\", \"firstName\": \"Test\", \"lastName\": \"User\"}"
```

**In local/testing** the response includes the code:

```json
{"success":true,"message":"Verification code sent.","code":"123456"}
```

Copy the `code` value.

### Step 2: Verify (replace `123456` with the code from step 1)

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/verify \
  -H "Content-Type: application/json" \
  -d "{\"phone\": \"01274235122\", \"code\": \"123456\", \"password\": \"Secret123\", \"password_confirmation\": \"Secret123\"}"
```

Response:

```json
{
  "success": true,
  "message": "Verified.",
  "token": "1|...",
  "token_type": "Bearer",
  "customer": { ... }
}
```

Use the `token` as `Authorization: Bearer <token>` for protected endpoints.

### Optional: Call a protected endpoint

```bash
curl -s http://127.0.0.1:8000/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Throttling

If you run signup twice within 60 seconds for the same phone, you’ll get “Please wait before requesting another code.” Wait a minute or use a different phone number.

## Troubleshooting

- **"no such table: personal_access_tokens"** – Run `php artisan migrate` so Sanctum's table exists.
- **Code not in signup response** – Ensure `APP_ENV=local` or `testing` so the API returns the code for testing.
