<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Support\ApiDateTime;
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
        $promo = PromoCodeModel::findByCode((string) $code);

        if (! $promo) {
            return response()->json([
                'valid' => false,
                'reason' => 'not_found',
                'message' => 'Promo code does not exist.',
            ], 200);
        }

        $reason = $promo->invalidReason();
        if ($reason !== null) {
            $payload = [
                'valid' => false,
                'reason' => $reason,
                'promo_code' => $this->promoCodeDetails($promo),
            ];

            return match ($reason) {
                'inactive' => response()->json([
                    ...$payload,
                    'message' => 'This promo code is not active.',
                ], 200),
                'not_yet_valid' => response()->json([
                    ...$payload,
                    'message' => 'This promo code is not yet valid.',
                    'valid_from' => ApiDateTime::toUtcIso8601($promo->valid_from),
                ], 200),
                'expired' => response()->json([
                    ...$payload,
                    'message' => 'This promo code has expired.',
                    'valid_until' => ApiDateTime::toUtcIso8601($promo->valid_until),
                ], 200),
                'usage_limit_reached' => response()->json([
                    ...$payload,
                    'message' => 'This promo code has reached its usage limit.',
                ], 200),
                default => response()->json([
                    ...$payload,
                    'message' => 'This promo code is not valid.',
                ], 200),
            };
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
            'valid_from' => ApiDateTime::toUtcIso8601($promo->valid_from),
            'valid_until' => ApiDateTime::toUtcIso8601($promo->valid_until),
            'usage_limit' => $promo->usage_limit,
            'used_count' => $promo->used_count,
            'is_active' => $promo->is_active,
        ];
    }
}
