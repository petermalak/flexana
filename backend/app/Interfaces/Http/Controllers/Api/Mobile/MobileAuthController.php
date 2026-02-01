<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Application\Auth\PhoneVerificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $verification,
    ) {
    }

    /**
     * POST /api/v1/auth/send-code
     * Body: { phone [, firstName, lastName, email ] }
     */
    public function sendCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
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
        $result = $this->verification->sendCode(
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

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ], 200);
    }

    /**
     * POST /api/v1/auth/verify
     * Body: { phone, code }
     * Returns token + customer profile.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'code' => 'required|string|size:6',
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
        $token = $customer->createToken('mobile')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'token' => $token,
            'token_type' => 'Bearer',
            'customer' => $this->customerToArray($customer),
        ], 200);
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke current token. Requires Authorization: Bearer <token>.
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
     * Returns current customer profile. Requires Authorization: Bearer <token>.
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
     * Update current customer profile.
     */
    public function updateMe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
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

        $customer = $request->user();
        $data = $validator->validated();
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
