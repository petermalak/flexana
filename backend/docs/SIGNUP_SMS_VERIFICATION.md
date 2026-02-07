# Signup: send real SMS to verify the phone number

## What happens when a new user signs up

1. The user enters their **phone number** (e.g. in your app or on a form).
2. Your **backend** asks **Firebase** to send an SMS with a **6-digit code** to that phone number.
3. **Firebase** sends a **real SMS** to the user’s phone (the message contains the code).
4. The **user** receives the SMS and enters the **code** in your app.
5. Your **app** sends the **phone + code** to the backend.
6. The **backend** checks the code with Firebase and, if it’s correct, **creates the user** and returns a **token** (the user is signed in).

So: **when a new user signs up, the system sends a real SMS to their phone number to verify it.** Firebase is the one that actually sends the SMS.

---

## How to test it (receive a real SMS on your phone)

You need to run this from a **browser** (Firebase requires a security check – reCAPTCHA – which only runs in a browser). A test page is included.

### Step 1: Get a reCAPTCHA key (one-time)

1. Open: **https://www.google.com/recaptcha/admin**
2. Click **Create**
3. Choose **reCAPTCHA v3**
4. Add domain: **localhost**
5. Copy the **Site key** (starts with `6L...`)

### Step 2: Start your backend

In a terminal:

```bash
cd backend
php artisan serve
```

### Step 3: Open the test page and use your phone number

1. In your browser open: **http://127.0.0.1:8000/test-firebase-sms.html**
2. Paste your **reCAPTCHA site key** in the first field.
3. Enter **your real phone number** (e.g. `+201274235122` for Egypt).
4. Click **“Send verification SMS to this number”**.
5. **Check your phone** – you should receive an SMS with a 6-digit code from Firebase.
6. Enter that **code** on the page and click **“Verify code and sign in”**.
7. You will get a **token** – that means signup + SMS verification worked.

That’s the full flow: **signup → real SMS to the phone → user enters code → verified and signed in.**

---

## In your real app (mobile or web)

- **Signup:** Call `POST /api/v1/auth/signup` with `phone` and `recaptchaToken` (your app gets the reCAPTCHA token in the browser or via SafetyNet/Play Integrity on mobile). The backend will ask Firebase to send the SMS; the user receives it.
- **Verify:** When the user enters the code, call `POST /api/v1/auth/verify-with-firebase-code` with `sessionInfo` (from the signup response), `code`, and `phone`. The backend returns the token.

The test page does exactly these two calls so you can see the real SMS on your phone.
