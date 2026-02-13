<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MobilePromoCodeController extends Controller
{
    /**
     * Verify a promo code.
     * Returns whether the code exists, is valid/expired/inactive/limit reached, and details when valid.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_request',
                'message' => 'Code is required.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = $request->input('code');
        $promo = PromoCodeModel::query()->where('code', $code)->first();

        if (! $promo) {
            return response()->json([
                'valid' => false,
                'reason' => 'not_found',
                'message' => 'Promo code does not exist.',
            ], 200);
        }

        if (! $promo->is_active) {
            return response()->json([
                'valid' => false,
                'reason' => 'inactive',
                'message' => 'This promo code is not active.',
                'promo_code' => $this->promoCodeDetails($promo),
            ], 200);
        }

        if ($promo->valid_from && now()->lt($promo->valid_from)) {
            return response()->json([
                'valid' => false,
                'reason' => 'not_yet_valid',
                'message' => 'This promo code is not yet valid.',
                'valid_from' => $promo->valid_from->toIso8601String(),
                'promo_code' => $this->promoCodeDetails($promo),
            ], 200);
        }

        if ($promo->valid_until && now()->gt($promo->valid_until)) {
            return response()->json([
                'valid' => false,
                'reason' => 'expired',
                'message' => 'This promo code has expired.',
                'valid_until' => $promo->valid_until->toIso8601String(),
                'promo_code' => $this->promoCodeDetails($promo),
            ], 200);
        }

        if ($promo->usage_limit !== null && $promo->used_count >= $promo->usage_limit) {
            return response()->json([
                'valid' => false,
                'reason' => 'usage_limit_reached',
                'message' => 'This promo code has reached its usage limit.',
                'promo_code' => $this->promoCodeDetails($promo),
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Promo code is valid.',
            'promo_code' => $this->promoCodeDetails($promo),
        ], 200);
    }

    private function promoCodeDetails(PromoCodeModel $promo): array
    {
        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'name' => $promo->name,
            'percent_discount' => (float) $promo->percent_discount,
            'valid_from' => $promo->valid_from?->toIso8601String(),
            'valid_until' => $promo->valid_until?->toIso8601String(),
            'usage_limit' => $promo->usage_limit,
            'used_count' => $promo->used_count,
            'is_active' => $promo->is_active,
        ];
    }
}
