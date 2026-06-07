<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Firebase project (flexana-test)
    |--------------------------------------------------------------------------
    | Used for: Auth token verification, Firestore migration, and Phone SMS verification.
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'flexana-test'),
    'api_key' => env('FIREBASE_API_KEY', 'AIzaSyCKtZV0sMtAqkj0EIqFw8ROAF7r1nS8X74'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'flexana-test.firebaseapp.com'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'flexana-test.firebasestorage.app'),
    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '1021918771595'),
    'app_id' => env('FIREBASE_APP_ID', '1:1021918771595:web:d95db7e3b821cfc239578e'),
    'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-DH6TCCBC12'),
    'gcm_sender_id' => env('FIREBASE_GCM_SENDER_ID', '1021918771595'),

    /*
    |--------------------------------------------------------------------------
    | Phone number verification (SMS)
    |--------------------------------------------------------------------------
    | Firebase sends the verification SMS. The backend uses:
    | - api_key: for sendVerificationCode and signInWithPhoneNumber (Identity Toolkit API).
    | - project_id: for verifying Firebase ID tokens (JWKS).
    |
    | Required in .env for Firebase SMS:
    |   FIREBASE_API_KEY=<Web API Key from Firebase Console>
    |   FIREBASE_PROJECT_ID=flexana-test  (optional if same as default)
    |
    | In Firebase Console: Authentication → Sign-in method → enable Phone.
    | For testing without real SMS: add test phone numbers under Phone → Phone numbers for testing.
    */

    // Phone verification via Firebase SMS has been removed.

    /*
    |--------------------------------------------------------------------------
    | Service account (FCM push, optional admin tools)
    |--------------------------------------------------------------------------
    |
    | Place firebase-service-account.json in storage/app/ on each server (not in git).
    | Optional FIREBASE_SERVICE_ACCOUNT_JSON: relative to project root, or absolute path.
    | When unset, defaults to storage/app/firebase-service-account.json.
    |
    */
    'service_account_json' => \App\Support\FirebaseServiceAccountPath::resolve(
        env('FIREBASE_SERVICE_ACCOUNT_JSON'),
    ),
];

