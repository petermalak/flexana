# Test Firebase SMS flow

Firebase sends the verification SMS. To test **without** a real SMS and without reCAPTCHA, use **Firebase test phone numbers**.

---

## 1. Add a test phone number in Firebase

1. Open [Firebase Console](https://console.firebase.google.com) → your project.
2. Go to **Authentication** → **Sign-in method** → **Phone** → enable it if needed.
3. Under **Phone numbers for testing**, click **Add phone number**.
4. Enter:
   - **Phone number:** `+201274235122` (E.164; for Egyptian 01274235122 use +201274235122).
   - **Verification code:** e.g. `123456` (the “code” the user will enter).
5. Save.

No real SMS is sent for test numbers; Firebase uses the code you set.

---

## 2. Set your Firebase API key

In `.env`:

```env
FIREBASE_API_KEY=your_web_api_key_here
```

Get it from Firebase Console → Project settings → General → Web API Key.

---

## 3. Run the test command

From the `backend` folder:

```bash
php artisan auth:test-firebase-sms 01274235122 --code=123456 --name="Test"
```

Use the **same code** you configured for that test number in step 1 (e.g. `123456`).

Optional: set a password for later phone+password login:

```bash
php artisan auth:test-firebase-sms 01274235122 --code=123456 --name="Test" --password=Secret123
```

If you omit `--code=`, the command will ask you for the code.

---

## 4. What the command does

1. Calls Firebase **sendVerificationCode** for the given phone (no reCAPTCHA; only works for **test** numbers).
2. Gets **sessionInfo** from Firebase.
3. Calls Firebase **signInWithPhoneNumber** with that sessionInfo and the code you provided.
4. Gets the Firebase idToken, finds or creates your customer, and issues a **Sanctum token**.

You should see the Bearer token in the output. Use it for authenticated API calls.

---

## If step 1 fails (e.g. RECAPTCHA error)

- Confirm the phone is added under **Phone numbers for testing** in Firebase (step 1).
- Use E.164 format: `+201274235122` for 01274235122.
- Ensure **FIREBASE_API_KEY** is set in `.env` and run `php artisan config:clear`.

---

## Testing with a real SMS (web test page)

For a real phone number, Firebase requires a **reCAPTCHA token** from the client (web or mobile app). The app must:

1. Get a reCAPTCHA token (Firebase reCAPTCHA verifier or reCAPTCHA v3).
2. Call **POST /api/v1/auth/signup** with `phone` and `recaptchaToken`.
3. Receive `sessionInfo`; show “Enter code” and send the code to **POST /api/v1/auth/verify-with-firebase-code** with `sessionInfo`, `code`, `phone`.

**To test with real SMS:** Open **http://127.0.0.1:8000/test-firebase-sms.html** (with `php artisan serve` running). Get a reCAPTCHA v3 site key from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin) (v3, domains localhost + 127.0.0.1). Paste the key on the page, enter your phone, click Send code – Firebase sends the SMS. Enter the code and verify to get the token.
