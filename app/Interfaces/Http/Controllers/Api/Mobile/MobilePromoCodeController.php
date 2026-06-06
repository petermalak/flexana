<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Domain\Promo\Enums\PromoApplicableType;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Support\ApiDateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
            'context' => ['required', 'string', Rule::in(array_column(PromoApplicableType::cases(), 'value'))],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_request',
                'message' => 'Code and context are required.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $code = $request->input('code');
        $context = PromoApplicableType::from((string) $request->input('context'));
        $promo = PromoCodeModel::findByCode((string) $code);

        if (! $promo) {
            return response()->json([
                'valid' => false,
                'reason' => 'not_found',
                'message' => 'Promo code does not exist.',
            ], 200);
        }

        /** @var \App\Models\Customer $customer */
        $customer = $request->user();
        $reason = $promo->invalidReasonForCustomer((int) $customer->id, $context);
        if ($reason !== null) {
            $payload = [
                'valid' => false,
                'reason' => $reason,
                'promo_code' => $this->promoCodeDetails($promo, (int) $customer->id),
            ];

            return match ($reason) {
                'inactive' => response()->json([
                    ...$payload,
                    'message' => 'This promo code is not active.',
                ], 200),
                'wrong_type' => response()->json([
                    ...$payload,
                    'message' => $this->wrongTypeMessage($promo),
                ], 200),
                'not_yet_valid' => response()->json([
                    ...$payload,
                    'message' => 'This promo code is not yet valid.',
                    'valid_from' => ApiDateTime::toBusinessIso8601($promo->valid_from),
                    'valid_from_utc' => ApiDateTime::toUtcIso8601($promo->valid_from),
                ], 200),
                'expired' => response()->json([
                    ...$payload,
                    'message' => 'This promo code has expired.',
                    'valid_until' => ApiDateTime::toBusinessIso8601($promo->valid_until),
                    'valid_until_utc' => ApiDateTime::toUtcIso8601($promo->valid_until),
                ], 200),
                'usage_limit_reached' => response()->json([
                    ...$payload,
                    'message' => 'You have already used this promo code the maximum number of times.',
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
            'promo_code' => $this->promoCodeDetails($promo, (int) $customer->id),
        ], 200);
    }

    private function wrongTypeMessage(PromoCodeModel $promo): string
    {
        $messages = config('promo.wrong_type_messages', []);
        $type = $promo->applicable_to?->value;

        return (string) ($type !== null ? ($messages[$type] ?? 'This promo code is not valid for this purchase.') : 'This promo code is not valid for this purchase.');
    }

    private function promoCodeDetails(PromoCodeModel $promo, ?int $customerId = null): array
    {
        $usesByYou = $customerId !== null ? $promo->redemptionCountForCustomer($customerId) : null;

        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'name' => $promo->name,
            'percent_discount' => (float) $promo->percent_discount,
            'applicable_to' => $promo->applicable_to?->value,
            'valid_from' => ApiDateTime::toBusinessIso8601($promo->valid_from),
            'valid_from_utc' => ApiDateTime::toUtcIso8601($promo->valid_from),
            'valid_until' => ApiDateTime::toBusinessIso8601($promo->valid_until),
            'valid_until_utc' => ApiDateTime::toUtcIso8601($promo->valid_until),
            'usage_limit_per_user' => $promo->usage_limit_per_user,
            'uses_by_you' => $usesByYou,
            'used_count_total' => $promo->used_count,
            'is_active' => $promo->is_active,
        ];
    }
}
