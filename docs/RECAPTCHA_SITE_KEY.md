# How to get the reCAPTCHA site key

You need this key so the **test page** (or your app) can get a reCAPTCHA token and send it to the backend. Firebase uses that token before sending a real SMS (to block bots).

## Steps

1. **Open the reCAPTCHA admin**
   - Go to: **https://www.google.com/recaptcha/admin**
   - Sign in with your Google account.

2. **Create a new site**
   - Click the **+** (plus) button or **“Create”**.

3. **Fill the form**
   - **Label:** e.g. `Flexana test` (any name).
   - **reCAPTCHA type:** select **reCAPTCHA v3** (not v2).
   - **Domains:** add:
     - `localhost`
     - `127.0.0.1`
   - (For production, add your real domain, e.g. `yourapp.com`.)
   - Accept the reCAPTCHA terms if asked.

4. **Submit**
   - Click **Submit**.

5. **Copy the keys**
   - You get two keys:
     - **Site key** – use this in the **browser** (test page or frontend). It’s safe to expose.
     - **Secret key** – use only on the **server**; we don’t need it for the Firebase SMS flow because Firebase does the check on their side.
   - For the test page and for “send SMS” you only need the **Site key** (starts with something like `6L...`). Paste it in the test page field.

That’s it. Use the **Site key** in the test page; you don’t need to configure the Secret key in Laravel for this flow.

---

## Why you can’t test “real SMS” from the API only (Postman/curl)

**Firebase only sends a real SMS if the request includes a valid reCAPTCHA token.**  
That token is created by:

- **Browser:** Google’s reCAPTCHA script runs in the page, watches user behavior, and returns a short‑lived token.
- **Mobile:** The app uses SafetyNet (Android) or App Check (iOS) and sends an equivalent token.

So:

- **Postman / curl / Artisan:** You have no browser and no app, so you cannot create a valid reCAPTCHA token. If you call the API without a token (or with a fake one), Firebase will not send the SMS and will return an error (e.g. RECAPTCHA or INVALID_APP_CREDENTIAL).
- **Test page in the browser:** The page loads reCAPTCHA, gets a real token, and sends it to your API. So when you click “Send SMS” on that page, the backend can forward a valid token to Firebase and Firebase will send the real SMS.

So:

- **Test real SMS:** Use the **browser test page** (with the reCAPTCHA **Site key**).
- **Test without real SMS:** Use **Firebase test phone numbers** in the Firebase Console and the Artisan command `php artisan auth:test-firebase-sms` (no reCAPTCHA needed for those numbers).

Summary: the API is the same; the only difference is where the reCAPTCHA token comes from. It can only come from a browser (or mobile app), not from Postman or the terminal, so “testing from the APIs” with Postman alone cannot trigger a real SMS.
