# Firebase configuration (flexana-test)

Backend uses Firebase for **phone number verification (SMS)** and for verifying ID tokens. The app uses the same project.

## Backend .env (required for Firebase SMS)

Add to your `.env`:

```env
# Required for Firebase phone verification (send SMS + verify code)
FIREBASE_API_KEY=AIzaSyCKtZV0sMtAqkj0EIqFw8ROAF7r1nS8X74
FIREBASE_PROJECT_ID=flexana-test
```

- **FIREBASE_API_KEY**: Firebase Web API Key (Firebase Console → Project settings → General).
- **FIREBASE_PROJECT_ID**: Project ID (default `flexana-test`).

Optional:

```env
# Optional: disable Firebase phone verification (e.g. use backend OTP only)
FIREBASE_PHONE_VERIFICATION_ENABLED=true

# Optional: service account JSON path to fetch user phone from Firebase (so client need not send phone)
FIREBASE_SERVICE_ACCOUNT_JSON=/path/to/serviceAccountKey.json
```

Then run: `php artisan config:clear`

---

## Client config (for web/mobile app)

Use this in your **web or mobile app** when initializing the Firebase JS SDK.

```javascript
// For Firebase JS SDK v7.20.0 and later, measurementId is optional
const firebaseConfig = {
  apiKey: "AIzaSyCKtZV0sMtAqkj0EIqFw8ROAF7r1nS8X74",
  authDomain: "flexana-test.firebaseapp.com",
  projectId: "flexana-test",
  storageBucket: "flexana-test.firebasestorage.app",
  messagingSenderId: "1021918771595",
  appId: "1:1021918771595:web:d95db7e3b821cfc239578e",
  measurementId: "G-DH6TCCBC12"
};
```

## Backend alignment

The Laravel backend (`config/firebase.php`) uses:

- **FIREBASE_API_KEY** / `api_key` → same as `apiKey` above (for sendVerificationCode, signInWithPhoneNumber, token verification)
- **FIREBASE_PROJECT_ID** → `flexana-test`

So phone verification (Firebase SMS) and token verification use the same Firebase project.

## Using in your app

- **Web:** Initialize Firebase with this config, then use Phone Auth (reCAPTCHA verifier) and call your backend with `recaptchaToken` and later `sessionInfo` + `code`.
- **Mobile (React Native / Flutter):** Use the same `apiKey`, `projectId`, etc. in your Firebase SDK init; then get the reCAPTCHA/equivalent token and call the backend APIs.
