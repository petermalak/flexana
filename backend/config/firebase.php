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

    'phone_verification' => [
        'enabled' => (bool) env('FIREBASE_PHONE_VERIFICATION_ENABLED', true),
        'api_key' => env('FIREBASE_API_KEY', 'AIzaSyCKtZV0sMtAqkj0EIqFw8ROAF7r1nS8X74'),
        'project_id' => env('FIREBASE_PROJECT_ID', 'flexana-test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Service account (optional)
    |--------------------------------------------------------------------------
    | Path to Firebase service account JSON. If set, the backend can fetch the
    | user's phone from Firebase after sign-in (so the client need not send phone in verify-firebase).
    */
    'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON', ''),
];

