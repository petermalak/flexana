# Fix Firebase Phone Auth on Android (Play Integrity / "Internal error")

When signup returns **400** with messages like *"Internal error encountered"* or *"Verification service error"*, Firebase is rejecting the `playIntegrityToken`. Fix it by configuring your Android app and Firebase project correctly.

---

## 1. Add your app’s SHA-256 to Firebase

Firebase must know your app so it can trust the Play Integrity token.

1. **Get your SHA-256**
   - **Debug builds:** In Android Studio: **Gradle** panel → **:app** → **Tasks** → **android** → double‑click **signingReport**. Copy the **SHA-256** from the run output.
   - Or in terminal: `./gradlew signingReport` (under `Variant: debug`).
   - **Release builds:** Use the SHA-256 of the keystore you use to sign the release APK (e.g. from Play App Signing).

2. **Register it in Firebase**
   - Open [Firebase Console](https://console.firebase.google.com) → your project.
   - **Project settings** (gear) → **Your apps**.
   - Select your **Android** app (same package name as the app that sends `playIntegrityToken`).
   - Click **Add fingerprint** → paste **SHA-256** → Save.

3. **Use the same key for the build you’re testing**
   - If you’re testing a **debug** build, the SHA-256 you added must be from your **debug** keystore.
   - If you added **release** SHA-256 only, debug builds will keep failing until you add the **debug** SHA-256 too.

---

## 2. Enable Phone sign-in in Firebase

- Firebase Console → **Authentication** → **Sign-in method**.
- Enable **Phone** and save.

---

## 3. Same project and API key

- The backend’s **FIREBASE_API_KEY** (in `.env`) must be the **Web API Key** of the **same** Firebase project where the Android app is registered.
- In Firebase: **Project settings** → **General** → **Web API Key**. Use that value in the backend.

---

## 4. Play Integrity API (Google Cloud)

- Open [Google Cloud Console](https://console.cloud.google.com) → select the **same** project linked to Firebase.
- **APIs & Services** → **Library** → search **Play Integrity API** → **Enable**.
- For apps that use **Google Play** (e.g. internal testing track), you may need to link the app in **Play Console** → **Release** → **App integrity** → **Play Integrity API** → link the Firebase/Cloud project. This is required when the app is distributed via Play.

---

## 5. Check backend logs for the real error

After a failed signup, the backend logs the exact Firebase response. Check `storage/logs/laravel.log` for:

- `Firebase sendVerificationCode failed`
- `firebase_error_code` (e.g. **18002**, **17028**)
- `firebase_message`

| Code   | Meaning | What to do |
|--------|--------|------------|
| **18002** | App not recognized by Play | Add the **correct** SHA-256 for the build you’re using (debug vs release) in Firebase. Ensure the app is the one registered for that package name. |
| **17028** | Invalid Play Integrity token | Same as above; also ensure the app is signed with the key that matches the SHA-256 you added. Enable Play Integrity API in Google Cloud. |
| **Internal error** (no code) | Often same as 18002/17028 | Follow steps 1–4; then retry and check logs again for a concrete code. |

---

## 6. Testing without Play Integrity (optional)

For **local/dev only**, you can use the backend’s **legacy OTP** flow so Firebase is not involved for sending the SMS:

- **Signup:** `POST /api/v1/auth/signup` with **only** `phone` (no `playIntegrityToken`). Backend sends OTP via Twilio or logs it.
- **Verify:** `POST /api/v1/auth/verify` with `phone`, `code`, and optional `password`.

For **real SMS with Firebase** in production, the Android app must use `playIntegrityToken` and the steps above must be done.

---

## Quick checklist

- [ ] SHA-256 of the **build you’re testing** (debug or release) added in Firebase → Project settings → Your apps → Android → Add fingerprint.
- [ ] Phone sign-in method enabled in Firebase Authentication.
- [ ] Backend `.env` has **FIREBASE_API_KEY** = Web API Key of that same project.
- [ ] Play Integrity API enabled in Google Cloud for that project.
- [ ] After a failed request, check `storage/logs/laravel.log` for `firebase_error_code` and `firebase_message` to confirm the cause.
