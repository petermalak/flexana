<?php

return [
    /*
    | Firebase project config (flexana-test).
    | Used for Auth token verification (admin) and Firestore migration.
    | Web config: apiKey, authDomain, projectId, storageBucket, messagingSenderId, appId, measurementId.
    */
    'project_id' => env('FIREBASE_PROJECT_ID', 'flexana-test'),
    'api_key' => env('FIREBASE_API_KEY', 'AIzaSyCKtZV0sMtAqkj0EIqFw8ROAF7r1nS8X74'),
    'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'flexana-test.firebaseapp.com'),
    'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'flexana-test.firebasestorage.app'),
    'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '1021918771595'),
    'app_id' => env('FIREBASE_APP_ID', '1:1021918771595:web:d95db7e3b821cfc239578e'),
    'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-DH6TCCBC12'),
    'gcm_sender_id' => env('FIREBASE_GCM_SENDER_ID', '1021918771595'),
];

