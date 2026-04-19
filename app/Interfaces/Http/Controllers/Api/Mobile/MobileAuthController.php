<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Application\Auth\PhoneVerificationService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\CustomerDeviceTokenModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Models\Customer;
use App\Support\ApiDateTime;
use App\Support\PhoneNumberNormalizer;
use App\Support\PackagePurchaseExpiry;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $verification,
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

        $password = $validator->validated()['password'];

        $customer = $this->verification->findCustomerForPhoneAuth($validator->validated()['phone']);
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
     * Body: { phone [, firstName, lastName, email, profileImage ] }
     * Pure backend OTP: we send a 6-digit code via SMS (Twilio/log), then the app calls POST /auth/verify with phone + code.
     * profileImage: base64 encoded image string or multipart/form-data file upload
     */
    public function signup(Request $request): JsonResponse
    {
        if ($request->filled('phone')) {
            $request->merge([
                'phone' => PhoneNumberNormalizer::normalize((string) $request->input('phone')),
            ]);
        }

        $validator = Validator::make($request->all(), [
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique('customers', 'phone')->whereNull('deleted_at'),
            ],
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => [
                'nullable',
                'email',
                Rule::unique('customers', 'email')->whereNull('deleted_at'),
            ],
            'profileImage' => $this->profileImageValidationRules($request),
        ], [
            'phone.unique' => 'This phone number is already registered. Please log in instead.',
            'email.unique' => 'This email is already registered.',
        ]);

        if ($validator->fails()) {
            $message = $this->signupHasDuplicatePhoneOrEmail($validator)
                ? 'Account details already exists'
                : 'Validation failed';

            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $profileImagePath = null;

        // Handle image upload if provided
        if ($request->hasFile('profileImage')) {
            $profileImagePath = $request->file('profileImage')->store('profiles', 'public');
        } elseif ($request->has('profileImage') && !empty($request->input('profileImage'))) {
            // Handle base64 encoded image
            $profileImagePath = $this->storeBase64Image($request->input('profileImage'), 'profiles');
        }

        // Backend OTP only: we send the SMS, app calls POST /auth/verify
        $result = $this->verification->sendSignupCode(
            $data['phone'],
            $data['firstName'] ?? null,
            $data['lastName'] ?? null,
            $data['email'] ?? null,
            $profileImagePath
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        $phone = $this->normalizePhone($data['phone']);
        $response = [
            'success' => true,
            'message' => $result['message'],
            'useLegacyVerify' => true,
            'phone' => $phone,
        ];
        if (app()->environment('local', 'testing') && isset($result['code'])) {
            $response['code'] = $result['code'];
        }

        return response()->json($response, 200);
    }

    /**
     * POST /api/v1/auth/verify
     * Body: { phone, code [, password, profileImage ] }
     * Verifies OTP. If password provided, sets it (for new signups). Returns token + customer.
     * profileImage: base64 encoded image string or multipart/form-data file upload
     */
    public function verify(Request $request): JsonResponse
    {
        // Temporary mobile compatibility: some app builds call /auth/verify for password reset.
        // If enabled, route such requests to /auth/reset-password — but never when this is a
        // signup verify (same body shape: phone, code, password, password_confirmation).
        if (config('sms.verify_password_reset_workaround') === true
            && $request->filled('password')
            && $request->filled('password_confirmation')) {
            $normalizedPhone = PhoneNumberNormalizer::normalize((string) $request->input('phone', ''));
            if (! $this->verification->hasPendingSignupVerification($normalizedPhone)) {
                Log::warning('Password reset via /auth/verify workaround', [
                    'phone' => (string) $request->input('phone', ''),
                ]);

                return $this->resetPassword($request);
            }
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
            'password' => ['nullable', 'string', 'confirmed', PasswordRule::min(8)],
            'profileImage' => $this->profileImageValidationRules($request),
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
        }

        // Handle image upload if provided
        if ($request->hasFile('profileImage')) {
            // Delete old image if exists
            if ($customer->profile_image) {
                Storage::disk('public')->delete($customer->profile_image);
            }
            $customer->profile_image = $request->file('profileImage')->store('profiles', 'public');
        } elseif ($request->has('profileImage') && !empty($request->input('profileImage'))) {
            // Handle base64 encoded image
            if ($customer->profile_image) {
                Storage::disk('public')->delete($customer->profile_image);
            }
            $customer->profile_image = $this->storeBase64Image($request->input('profileImage'), 'profiles');
        }

        $customer->save();

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

    // All Firebase-based signup/verification endpoints have been removed.
    // Phone verification is now done entirely in the backend via OTP (see signup + verify).

    /**
     * POST /api/v1/auth/forgot-password
     * Body: { phone }
     * Sends password reset OTP code to customer's phone via SMS.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->verification->sendPasswordResetCode($validator->validated()['phone']);

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

        // Include code in development/testing environments
        if (app()->environment('local', 'testing') && isset($result['code'])) {
            $response['code'] = $result['code'];
        }

        return response()->json($response, 200);
    }

    /**
     * POST /api/v1/auth/reset-password
     * Body: { phone, code, password, password_confirmation }
     * Verifies the OTP code sent to phone and resets the password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $result = $this->verification->verifyPasswordResetCode($data['phone'], $data['code']);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        /** @var \App\Models\Customer $customer */
        $customer = $result['customer'];
        $customer->password = Hash::make($data['password']);
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Password has been reset successfully.',
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
     * To update profile image: send profileImage (file upload or base64 string).
     */
    public function updateMe(Request $request): JsonResponse
    {
        $customer = $request->user();
        $validator = Validator::make($request->all(), [
            'firstName' => 'nullable|string|max:100',
            'lastName' => 'nullable|string|max:100',
            'email' => [
                'nullable',
                'email',
                Rule::unique('customers', 'email')
                    ->ignore($customer->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => 'nullable|string|max:20',
            'phoneChangeCode' => 'nullable|string|size:6',
            'profileImage' => $this->profileImageValidationRules($request),
        ], [
            'email.unique' => 'This email is already used by another account.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

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

        // Handle image upload if provided
        if ($request->hasFile('profileImage')) {
            // Delete old image if exists
            if ($customer->profile_image) {
                Storage::disk('public')->delete($customer->profile_image);
            }
            $customer->profile_image = $request->file('profileImage')->store('profiles', 'public');
        } elseif ($request->has('profileImage') && !empty($request->input('profileImage'))) {
            // Handle base64 encoded image
            if ($customer->profile_image) {
                Storage::disk('public')->delete($customer->profile_image);
            }
            $customer->profile_image = $this->storeBase64Image($request->input('profileImage'), 'profiles');
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

    /**
     * Signup: phone or email failed the unique rule (active customer already using them).
     */
    private function signupHasDuplicatePhoneOrEmail(ValidatorContract $validator): bool
    {
        foreach (['phone', 'email'] as $field) {
            foreach (array_keys($validator->failed()[$field] ?? []) as $rule) {
                if (strcasecmp((string) $rule, 'Unique') === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function normalizePhone(string $phone): string
    {
        return PhoneNumberNormalizer::normalize($phone);
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

    /**
     * Aligns with MobileSessionController::getCategoryFilterData() heuristics for category name/slug.
     */
    private function packageCategoryFromServiceCategory(?CategoryModel $category): ?string
    {
        if (! $category) {
            return null;
        }
        $nl = strtolower((string) ($category->name ?? ''));
        $sl = strtolower((string) ($category->slug ?? ''));
        if ($sl === 'yoga' || (str_contains($nl, 'yoga') && ! str_contains($nl, 'pilates'))) {
            return 'Yoga';
        }
        if (in_array($sl, ['reformer-pilates', 'pilates', 'reformer'], true)
            || str_contains($nl, 'reformer')
            || (str_contains($nl, 'pilates') && ! str_contains($nl, 'yoga'))) {
            return 'Reformer Pilates';
        }

        return null;
    }

    /**
     * Determine package category (Yoga / Reformer Pilates) from package.service_type (admin-set) first,
     * then service names, then service.category (name/slug) — consistent with sessions API.
     * Returns 'Yoga', 'Reformer Pilates', or null.
     */
    private function packageServiceType($package): ?string
    {
        if (! $package) {
            return null;
        }
        // Prioritize admin-set service_type field (authoritative source)
        $serviceType = $package->service_type ?? null;
        if ($serviceType !== null) {
            $lower = strtolower((string) $serviceType);
            if (str_contains($lower, 'reformer') || str_contains($lower, 'reform')) {
                return 'Reformer Pilates';
            }
            if (str_contains($lower, 'yoga')) {
                return 'Yoga';
            }
        }
        // Fallback: determine from service names if service_type is not set
        if ($package->relationLoaded('services') && $package->services->isNotEmpty()) {
            $yogaCount = 0;
            $reformerCount = 0;
            foreach ($package->services as $service) {
                $name = strtolower($service->name ?? '');
                if (str_contains($name, 'reformer') || str_contains($name, 'reform pilates')) {
                    $reformerCount++;
                } elseif (str_contains($name, 'yoga')) {
                    $yogaCount++;
                }
            }
            if ($reformerCount > 0 && $reformerCount >= $yogaCount) {
                return 'Reformer Pilates';
            }
            if ($yogaCount > 0) {
                return 'Yoga';
            }
            // Fallback: service.category (name/slug), same idea as MobileSessionController
            $yogaCatCount = 0;
            $reformerCatCount = 0;
            foreach ($package->services as $service) {
                if ($service->relationLoaded('category') && $service->category) {
                    $catType = $this->packageCategoryFromServiceCategory($service->category);
                    if ($catType === 'Reformer Pilates') {
                        $reformerCatCount++;
                    } elseif ($catType === 'Yoga') {
                        $yogaCatCount++;
                    }
                }
            }
            if ($reformerCatCount > 0 && $reformerCatCount >= $yogaCatCount) {
                return 'Reformer Pilates';
            }
            if ($yogaCatCount > 0) {
                return 'Yoga';
            }
        }

        return null;
    }

    /**
     * When customer_package_purchases.package_id is null but amelia_package_id is set, resolve the Package row.
     */
    private function resolvePackageForPurchase($purchase): ?PackageModel
    {
        $p = $purchase->package;
        if ($p instanceof PackageModel) {
            return $p;
        }
        $ameliaId = $purchase->amelia_package_id ?? null;
        if ($ameliaId === null || (int) $ameliaId === 0) {
            return null;
        }

        return PackageModel::query()
            ->where('amelia_package_id', (int) $ameliaId)
            ->with(['services.category', 'classType'])
            ->first();
    }

    /**
     * From valid package candidates (same category), return the last purchased one (by purchase date).
     * Drops the internal _purchase_date key from the returned detail.
     */
    private function lastValidPackageFromCandidates(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }
        $last = collect($candidates)->sortByDesc(function ($detail) {
            $d = $detail['_purchase_date'] ?? null;
            return $d ? $d->format('Y-m-d H:i:s') : '';
        })->first();
        unset($last['_purchase_date']);
        return $last;
    }

    /**
     * From valid package candidates (same category), return the first purchased one (by purchase date).
     * Drops the internal _purchase_date key from the returned detail.
     */
    private function oldestValidPackageFromCandidates(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }
        $oldest = collect($candidates)->sortBy(function ($detail) {
            $d = $detail['_purchase_date'] ?? null;
            return $d ? $d->format('Y-m-d H:i:s') : '9999-12-31 23:59:59';
        })->first();
        unset($oldest['_purchase_date']);
        return $oldest;
    }

    private function sumRemainingSessionsFromCandidates(array $candidates): int
    {
        $sum = 0;
        foreach ($candidates as $detail) {
            $sum += (int) ($detail['remainingSessions'] ?? 0);
        }
        return $sum;
    }

    private function customerToArray($customer): array
    {
        $activePurchases = $customer->packagePurchases()
            ->with(['package.services.category', 'package.classType'])
            ->where('status', 'active')
            ->get();

        $bizTz = (string) config('app.business_timezone');
        $today = Carbon::now($bizTz)->startOfDay();
        $yogaCandidates = [];
        $reformerCandidates = [];
        $unclassifiedCandidates = [];
        foreach ($activePurchases as $purchase) {
            $remaining = (int) $purchase->remaining_sessions;
            if ($remaining <= 0) {
                continue;
            }
            $package = $this->resolvePackageForPurchase($purchase);
            $expiresAt = PackagePurchaseExpiry::expiresAt(
                $package,
                $purchase->purchase_date,
                $purchase->amelia_package_id,
                (bool) $purchase->expires_by_months_only,
            );
            if ($expiresAt !== null && $expiresAt->copy()->timezone($bizTz)->startOfDay()->lt($today)) {
                continue;
            }
            $resolvedPackageId = $package?->id ?? $purchase->package_id ?? $purchase->amelia_package_id;
            $detail = [
                'packageId' => $resolvedPackageId !== null ? (string) $resolvedPackageId : '',
                'packageName' => $package ? ($package->title ?? '') : '',
                'remainingSessions' => $remaining,
                'totalSessions' => (int) $purchase->total_sessions,
                'purchaseDate' => ApiDateTime::toBusinessDateString($purchase->purchase_date),
                'purchaseAt' => ApiDateTime::toBusinessIso8601($purchase->purchase_date),
                'purchaseAtUtc' => ApiDateTime::toUtcIso8601($purchase->purchase_date),
                'expiresAt' => $expiresAt ? ApiDateTime::toBusinessDateString($expiresAt) : null,
                '_purchase_date' => $purchase->purchase_date,
                '_expires_at' => $expiresAt,
            ];
            $serviceType = $this->packageServiceType($package);
            if ($serviceType === 'Yoga') {
                $yogaCandidates[] = $detail;
            } elseif ($serviceType === 'Reformer Pilates') {
                $reformerCandidates[] = $detail;
            } else {
                $unclassifiedCandidates[] = $detail;
            }
        }
        unset($today);

        $yogaPackage = $this->oldestExpiringPackageFromCandidates($yogaCandidates);
        $reformerPackage = $this->oldestExpiringPackageFromCandidates($reformerCandidates);
        $unclassifiedPackage = $this->lastValidPackageFromCandidates($unclassifiedCandidates);

        // Sum remaining sessions across all valid purchases per category; nested package objects still use the latest purchase for metadata (e.g. expiresAt).
        $remainingYogaSessions = $this->sumRemainingSessionsFromCandidates($yogaCandidates);
        $remainingReformerSessions = $this->sumRemainingSessionsFromCandidates($reformerCandidates);
        $remainingUnclassifiedSessions = $this->sumRemainingSessionsFromCandidates($unclassifiedCandidates);
        if ($yogaPackage !== null) {
            $yogaPackage['remainingSessions'] = $remainingYogaSessions;
        }
        if ($reformerPackage !== null) {
            $reformerPackage['remainingSessions'] = $remainingReformerSessions;
        }
        if ($unclassifiedPackage !== null) {
            $unclassifiedPackage['remainingSessions'] = $remainingUnclassifiedSessions;
        }
        $remainingSessions = $remainingYogaSessions + $remainingReformerSessions + $remainingUnclassifiedSessions;

        $profileImageUrl = null;
        if ($customer->profile_image) {
            $profileImageUrl = $this->publicStorageUrl($customer->profile_image);
        }

        return [
            'id' => (string) $customer->id,
            'uid' => $customer->uid,
            'firstName' => $customer->first_name,
            'lastName' => $customer->last_name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'profileImage' => $profileImageUrl,
            'phoneVerifiedAt' => ApiDateTime::toBusinessIso8601($customer->phone_verified_at),
            'phoneVerifiedAtUtc' => ApiDateTime::toUtcIso8601($customer->phone_verified_at),
            'emailVerifiedAt' => ApiDateTime::toBusinessIso8601($customer->email_verified_at),
            'emailVerifiedAtUtc' => ApiDateTime::toUtcIso8601($customer->email_verified_at),
            'remainingSessions' => $remainingSessions,
            'remainingSessionsDetail' => [
                'remainingYogaSessions' => $remainingYogaSessions,
                'remainingReformerSessions' => $remainingReformerSessions,
                'remainingUnclassifiedSessions' => $remainingUnclassifiedSessions,
                'yogaPackage' => $yogaPackage,
                'reformerPackage' => $reformerPackage,
                'unclassifiedPackage' => $unclassifiedPackage,
            ],
        ];
    }

    /**
     * From valid package candidates (same category), return the one that expires soonest (by expiresAt).
     * Falls back to purchase date if expiresAt is null.
     * Drops internal keys from the returned detail.
     */
    private function oldestExpiringPackageFromCandidates(array $candidates): ?array
    {
        if ($candidates === []) {
            return null;
        }
        $oldest = collect($candidates)->sortBy(function ($detail) {
            $e = $detail['_expires_at'] ?? null;
            if ($e instanceof Carbon) {
                return $e->format('Y-m-d H:i:s');
            }
            $d = $detail['_purchase_date'] ?? null;
            return $d instanceof Carbon ? ('9999-12-31 23:59:59|' . $d->format('Y-m-d H:i:s')) : '9999-12-31 23:59:59|9999-12-31 23:59:59';
        })->first();
        unset($oldest['_purchase_date'], $oldest['_expires_at']);
        return $oldest;
    }

    /**
     * URL for a file on the public disk. Uses serve-storage.php so images work on servers
     * where /storage/ rewrite returns 404 (e.g. symlink or AllowOverride).
     */
    private function publicStorageUrl(string $path): string
    {
        return url('serve-storage.php') . '?path=' . rawurlencode($path);
    }

    /**
     * Validation rules for profileImage: file upload (image|mimes|max 5MB) or base64 string (string|max length ~7MB chars).
     */
    private function profileImageValidationRules(Request $request): array
    {
        $rules = ['nullable'];
        if ($request->hasFile('profileImage')) {
            $rules[] = 'image';
            $rules[] = 'mimes:jpeg,png,jpg,gif';
            $rules[] = 'max:5120'; // 5MB in KB
        } else {
            // Base64 string: 5MB binary ≈ 6.67MB base64; allow up to 7MB characters
            $rules[] = 'string';
            $rules[] = 'max:' . (7 * 1024 * 1024);
        }
        return $rules;
    }

    private function storeBase64Image(string $base64String, string $directory = 'profiles'): ?string
    {
        try {
            // Check if it's a valid base64 image string
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $imageData = substr($base64String, strpos($base64String, ',') + 1);
                $imageType = $matches[1];
            } else {
                // Assume it's raw base64
                $imageData = $base64String;
                $imageType = 'png'; // default
            }

            $decodedImage = base64_decode($imageData, true);
            if ($decodedImage === false) {
                return null;
            }

            // Generate unique filename
            $filename = uniqid('profile_', true) . '.' . $imageType;
            $path = $directory . '/' . $filename;

            // Store the image
            Storage::disk('public')->put($path, $decodedImage);

            return $path;
        } catch (\Exception $e) {
            Log::error('Failed to store base64 image: ' . $e->getMessage());
            return null;
        }
    }
}
