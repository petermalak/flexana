<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Application\Auth\FirebaseSendVerificationCodeService;
use App\Application\Auth\FirebaseSignInWithPhoneService;
use App\Application\Auth\PhoneVerificationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Auth\FirebaseTokenVerifier;
use App\Infrastructure\Persistence\Eloquent\CustomerDeviceTokenModel;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $verification,
        private readonly FirebaseTokenVerifier $firebaseVerifier,
        private readonly FirebaseSendVerificationCodeService $firebaseSendCode,
        private readonly FirebaseSignInWithPhoneService $firebaseSignIn,
    ) {
    }

    /**
     * POST /api/v1/auth/login
     * Body: { phone, password [, fcmToken, platform, deviceId ] }
     * Returns token + customer. Optional fcmToken (FCM device token) is stored for push notifications.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'password' => 'required|string',
            'fcmToken' => 'nullable|string|max:500',
            'platform' => 'nullable|string|in:android,ios',
            'deviceId' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $phone = $this->normalizePhone($validator->validated()['phone']);
        $password = $validator->validated()['password'];

        $customer = Customer::query()->where('phone', $phone)->first();
        if (! $customer || ! $customer->password || ! Hash::check($password, $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone or password.',
            ], 401);
        }

        $token = $customer->createToken('mobile')->plainTextToken;

        $data = $validator->validated();
        if (! empty($data['fcmToken'])) {
            $this->storeFcmToken($customer, $data['fcmToken'], $data['platform'] ?? null, $data['deviceId'] ?? null);
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged in.',
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * POST /api/v1/auth/signup
     * Body: { phone [, firstName, lastName, email ] } for backend OTP,
     *       or for Firebase SMS send one of:
     *       - recaptchaToken (+ recaptchaVersion) for web / testing,
     *       - playIntegrityToken for Android (Play Integrity),
     *       - safetyNetToken for Android (SafetyNet),
     *       - iosReceipt + iosSecret for iOS.
     * When a Firebase verification token is sent, Firebase sends the SMS and we return sessionInfo; then call verify-with-firebase-code.
     */
    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'recaptchaToken' => 'nullable|string',
            'recaptchaVersion' => 'nullable|string|in:v2,v3',
            'playIntegrityToken' => 'nullable|string',
            'safetyNetToken' => 'nullable|string',
            'iosReceipt' => 'nullable|string',
            'iosSecret' => 'nullable|string',
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $hasFirebaseToken = ! empty($data['recaptchaToken']) || ! empty($data['playIntegrityToken'])
            || ! empty($data['safetyNetToken']) || ! empty($data['iosReceipt']);

        // Firebase SMS: mobile app or web sent a verification token – use Firebase to send the SMS
        if ($hasFirebaseToken) {
            $result = $this->firebaseSendCode->sendCode($data['phone'], [
                'recaptchaToken' => $data['recaptchaToken'] ?? null,
                'recaptchaVersion' => $data['recaptchaVersion'] ?? null,
                'playIntegrityToken' => $data['playIntegrityToken'] ?? null,
                'safetyNetToken' => $data['safetyNetToken'] ?? null,
                'iosReceipt' => $data['iosReceipt'] ?? null,
                'iosSecret' => $data['iosSecret'] ?? null,
            ]);
            if (! $result['success']) {
                return response()->json(['success' => false, 'message' => $result['message']], 400);
            }
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'sessionInfo' => $result['sessionInfo'],
            ], 200);
        }

        // Backend OTP (our 6-digit code, sent via Twilio or logged)
        $result = $this->verification->sendSignupCode(
            $data['phone'],
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['email'] ?? null
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $response = [
            'success' => true,
            'message' => $result['message'],
        ];
        if (app()->environment('local', 'testing') && isset($result['code'])) {
            $response['code'] = $result['code'];
        }

        return response()->json($response, 200);
    }

    /**
     * POST /api/v1/auth/verify
     * Body: { phone, code [, password ] }
     * Verifies OTP. If password provided, sets it (for new signups). Returns token + customer.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::min(8)],
            'fcmToken' => 'nullable|string|max:500',
            'platform' => 'nullable|string|in:android,ios',
            'deviceId' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $result = $this->verification->verifyCode($data['phone'], $data['code']);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        /** @var \App\Models\Customer $customer */
        $customer = $result['customer'];
        if (! empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
            $customer->save();
        }

        $token = $customer->createToken('mobile')->plainTextToken;

        $data = $validator->validated();
        if (! empty($data['fcmToken'])) {
            $this->storeFcmToken($customer, $data['fcmToken'], $data['platform'] ?? null, $data['deviceId'] ?? null);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * POST /api/v1/auth/send-firebase-verification-code
     * Body: { phoneNumber } plus one of: recaptchaToken (web), playIntegrityToken (Android), safetyNetToken (Android), iosReceipt+iosSecret (iOS).
     * Sends SMS via Firebase. Returns sessionInfo; then call verify-with-firebase-code with sessionInfo + code + phone.
     */
    public function sendFirebaseVerificationCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phoneNumber' => 'required|string|max:20',
            'recaptchaToken' => 'nullable|string',
            'recaptchaVersion' => 'nullable|string|in:v2,v3',
            'playIntegrityToken' => 'nullable|string',
            'safetyNetToken' => 'nullable|string',
            'iosReceipt' => 'nullable|string',
            'iosSecret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $result = $this->firebaseSendCode->sendCode($data['phoneNumber'], [
            'recaptchaToken' => $data['recaptchaToken'] ?? null,
            'recaptchaVersion' => $data['recaptchaVersion'] ?? null,
            'playIntegrityToken' => $data['playIntegrityToken'] ?? null,
            'safetyNetToken' => $data['safetyNetToken'] ?? null,
            'iosReceipt' => $data['iosReceipt'] ?? null,
            'iosSecret' => $data['iosSecret'] ?? null,
        ]);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'sessionInfo' => $result['sessionInfo'],
        ], 200);
    }

    /**
     * POST /api/v1/auth/verify-firebase
     * Body: { idToken [, phone, firstName, lastName, email ] }
     * After Firebase phone sign-in on the client, send the Firebase ID token here.
     * Phone is required if your backend does not use Firebase Admin to fetch user (we verify token and find/create customer by firebase_uid).
     */
    public function verifyFirebase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'idToken' => 'required|string',
            'phone' => 'nullable|string|max:20',
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => 'nullable|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $firebaseToken = $this->firebaseVerifier->verify($validator->validated()['idToken']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Firebase token.',
            ], 401);
        }

        $uid = $firebaseToken->uid;
        $data = $validator->validated();
        $phone = ! empty($data['phone']) ? $this->normalizePhone($data['phone']) : null;

        if (empty($phone)) {
            $phone = $this->getPhoneFromFirebaseUser($uid);
        }
        if (empty($phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number is required. Send it in the request body after verifying with Firebase on the client.',
            ], 422);
        }

        $customer = Customer::query()
            ->where('firebase_uid', $uid)
            ->orWhere('uid', $uid)
            ->first();

        if (! $customer) {
            $customer = new Customer();
            $customer->firebase_uid = $uid;
            $customer->uid = (string) \Illuminate\Support\Str::uuid();
            $customer->phone = $phone;
        } else {
            $customer->phone = $phone;
        }

        $customer->phone_verified_at = now();
        if (! empty($data['firstName'])) {
            $customer->first_name = $data['firstName'];
        }
        if (! empty($data['lastName'])) {
            $customer->last_name = $data['lastName'];
        }
        if (array_key_exists('email', $data)) {
            $customer->email = $data['email'];
        }
        $customer->save();

        $token = $customer->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Verified.',
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * POST /api/v1/auth/verify-with-firebase-code
     * Body: { sessionInfo, code, phone [, firstName, lastName, email, password, password_confirmation ] }
     * Use after Firebase sent the SMS (via send-firebase-verification-code). User enters code; app sends
     * sessionInfo + code + phone. Backend exchanges them for Firebase idToken, then finds/creates customer and returns Sanctum token.
     */
    public function verifyWithFirebaseCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sessionInfo' => 'required|string',
            'code' => 'required|string|min:4|max:10',
            'phone' => 'required|string|max:20',
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::min(8)],
            'fcmToken' => 'nullable|string|max:500',
            'platform' => 'nullable|string|in:android,ios',
            'deviceId' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $signInResult = $this->firebaseSignIn->signIn($data['sessionInfo'], $data['code']);

        if (! $signInResult['success']) {
            return response()->json([
                'success' => false,
                'message' => $signInResult['message'],
            ], 400);
        }

        try {
            $firebaseToken = $this->firebaseVerifier->verify($signInResult['idToken']);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification result.',
            ], 401);
        }

        $uid = $firebaseToken->uid;
        $phone = $this->normalizePhone($data['phone']);

        $customer = Customer::query()
            ->where('firebase_uid', $uid)
            ->orWhere('uid', $uid)
            ->first();

        if (! $customer) {
            $customer = new Customer();
            $customer->firebase_uid = $uid;
            $customer->uid = (string) \Illuminate\Support\Str::uuid();
            $customer->phone = $phone;
        } else {
            $customer->phone = $phone;
        }

        $customer->phone_verified_at = now();
        if (! empty($data['firstName'])) {
            $customer->first_name = $data['firstName'];
        }
        if (! empty($data['lastName'])) {
            $customer->last_name = $data['lastName'];
        }
        if (array_key_exists('email', $data)) {
            $customer->email = $data['email'];
        }
        if (! empty($data['password'])) {
            $customer->password = Hash::make($data['password']);
        }
        $customer->save();

        $token = $customer->createToken('mobile')->plainTextToken;

        $data = $validator->validated();
        if (! empty($data['fcmToken'])) {
            $this->storeFcmToken($customer, $data['fcmToken'], $data['platform'] ?? null, $data['deviceId'] ?? null);
        }

        return response()->json([
            'success' => true,
            'message' => 'Verified.',
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * If Firebase Admin SDK is configured, fetch user by uid to get phone. Otherwise return null.
     */
    private function getPhoneFromFirebaseUser(string $uid): ?string
    {
        $path = config('firebase.service_account_json');
        if (empty($path) || ! is_file($path)) {
            return null;
        }

        try {
            if (class_exists(\Kreait\Firebase\Factory::class)) {
                $factory = (new \Kreait\Firebase\Factory)->withServiceAccount($path);
                $auth = $factory->createAuth();
                $user = $auth->getUser($uid);

                return $user->phoneNumber ?? null;
            }
        } catch (\Throwable) {
            // Ignore; caller will require phone in body
        }

        return null;
    }

    /**
     * POST /api/v1/auth/forgot-password
     * Body: { email }
     * Sends password reset link to customer's email.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::broker('customers')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'If that email exists, we have sent a password reset link.',
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'If that email exists, we have sent a password reset link.',
        ], 200);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Body: { email, token, password, password_confirmation }
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = Password::broker('customers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($customer, $password) {
                $customer->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset.',
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ], 200);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'customer' => $this->customerToArray($request->user()),
        ], 200);
    }

    /**
     * PUT /api/v1/auth/me
     * To change phone: send new phone + phoneChangeCode (from send-phone-change-code).
     */
    public function updateMe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'phoneChangeCode' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $request->user();
        $data = $validator->validated();

        if (isset($data['phone'])) {
            if (empty($data['phoneChangeCode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'To change phone, request a code first via POST /auth/send-phone-change-code, then send phone and phoneChangeCode.',
                ], 422);
            }
            $result = $this->verification->verifyPhoneChangeCode(
                $data['phone'],
                $data['phoneChangeCode'],
                (int) $customer->id
            );
            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }
            $customer->phone = $result['phone'];
            $customer->phone_verified_at = now();
        }

        if (isset($data['firstName'])) {
            $customer->first_name = $data['firstName'];
        }
        if (array_key_exists('lastName', $data)) {
            $customer->last_name = $data['lastName'];
        }
        if (array_key_exists('email', $data)) {
            $customer->email = $data['email'];
        }
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated.',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * POST /api/v1/auth/send-phone-change-code
     * Body: { newPhone }
     * Sends OTP to new phone. Then use PUT auth/me with phone + phoneChangeCode to confirm.
     */
    public function sendPhoneChangeCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'newPhone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $request->user();
        $result = $this->verification->sendPhoneChangeCode(
            $validator->validated()['newPhone'],
            (int) $customer->id
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }

    /**
     * POST /api/v1/auth/change-password
     * Body: { currentPassword, password, password_confirmation }
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = $request->user();
        if (! $customer->password || ! Hash::check($validator->validated()['currentPassword'], $customer->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 400);
        }

        $customer->password = Hash::make($validator->validated()['password']);
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed.',
        ], 200);
    }

    /**
     * POST /api/v1/auth/fcm-token
     * Body: { fcmToken [, platform, deviceId ] }
     * Register or update the FCM device token for push notifications (e.g. when token is refreshed).
     */
    public function registerFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcmToken' => 'required|string|max:500',
            'platform' => 'nullable|string|in:android,ios',
            'deviceId' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        /** @var Customer $customer */
        $customer = $request->user();
        $this->storeFcmToken($customer, $data['fcmToken'], $data['platform'] ?? null, $data['deviceId'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'FCM token registered.',
        ], 200);
    }

    /**
     * DELETE /api/v1/auth/delete-account
     * Permanently deletes the customer account and revokes all tokens.
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $customer = $request->user();
        $customer->tokens()->delete();
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted.',
        ], 200);
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', $phone);
    }

    /**
     * Store or update FCM device token for push notifications.
     * Creates a row if it does not exist, or updates it if it exists (by customer_id + device_id).
     * When deviceId is omitted, uses empty string so there is one token per customer.
     */
    private function storeFcmToken(Customer $customer, string $fcmToken, ?string $platform, ?string $deviceId): void
    {
        $key = [
            'customer_id' => $customer->id,
            'device_id' => $deviceId !== null && $deviceId !== '' ? $deviceId : '',
        ];

        CustomerDeviceTokenModel::query()->updateOrCreate($key, [
            'fcm_token' => $fcmToken,
            'platform' => $platform ? strtolower($platform) : null,
        ]);
    }

    private function customerToArray($customer): array
    {
        return [
            'id' => (string) $customer->id,
            'uid' => $customer->uid,
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'phoneVerifiedAt' => $customer->phone_verified_at?->toIso8601String(),
            'emailVerifiedAt' => $customer->email_verified_at?->toIso8601String(),
        ];
    }
}
