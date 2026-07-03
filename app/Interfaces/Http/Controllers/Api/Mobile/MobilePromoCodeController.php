<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Domain\Promo\Enums\PromoApplicableType;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
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
     *
     * Body: { code, IsPackage } where IsPackage=true checks package promos, false checks drop-in promos.
     * Legacy: { code, context } with context=packages|drop_ins|both still accepted.
     */
    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:64',
            'IsPackage' => 'nullable|boolean',
            'isPackage' => 'nullable|boolean',
            'context' => ['nullable', 'string', Rule::in(array_column(PromoApplicableType::cases(), 'value'))],
            'sessionID' => 'nullable|integer|exists:appointments,id',
            'appointmentId' => 'nullable|integer|exists:appointments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_request',
                'message' => 'Code and IsPackage are required.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $context = $this->resolvePromoContext($request);
        if ($context === null) {
            return response()->json([
                'valid' => false,
                'reason' => 'invalid_request',
                'message' => 'IsPackage is required (true for package purchase, false for drop-in booking).',
                'errors' => [
                    'IsPackage' => ['The IsPackage field is required.'],
                ],
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

        /** @var \App\Models\Customer $customer */
        $customer = $request->user();
        $appointmentId = $this->resolveAppointmentId($request);
        $branchId = $this->resolveBranchId($appointmentId);
        $reason = $promo->invalidReasonForCustomer((int) $customer->id, $context, $appointmentId, $branchId);
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
                'wrong_appointment' => response()->json([
                    ...$payload,
                    'message' => (string) config('promo.wrong_appointment_message', 'This promo code is not valid for the selected session.'),
                ], 200),
                'wrong_branch' => response()->json([
                    ...$payload,
                    'message' => (string) config('promo.wrong_branch_message', 'This promo code is not valid for the selected branch.'),
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

    /**
     * IsPackage=true → package purchase; false → drop-in booking.
     * Falls back to legacy `context` when IsPackage is omitted.
     */
    private function resolvePromoContext(Request $request): ?PromoApplicableType
    {
        if ($request->has('IsPackage') || $request->has('isPackage')) {
            $isPackage = filter_var(
                $request->input('IsPackage', $request->input('isPackage')),
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            );

            if ($isPackage === null) {
                return null;
            }

            return $isPackage ? PromoApplicableType::Packages : PromoApplicableType::DropIns;
        }

        if ($request->filled('context')) {
            return PromoApplicableType::from((string) $request->input('context'));
        }

        return null;
    }

    private function resolveAppointmentId(Request $request): ?int
    {
        $raw = $request->input('sessionID', $request->input('appointmentId'));

        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    private function resolveBranchId(?int $appointmentId): ?int
    {
        if ($appointmentId === null) {
            return null;
        }

        $branchId = AppointmentModel::query()
            ->whereKey($appointmentId)
            ->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
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
        $restrictedToAppointments = $promo->isRestrictedToAppointments();
        $restrictedToBranches = $promo->isRestrictedToBranches();

        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'name' => $promo->name,
            'percent_discount' => (float) $promo->percent_discount,
            'applicable_to' => $promo->applicable_to?->value,
            'restricted_to_appointments' => $restrictedToAppointments,
            'allowed_appointment_ids' => $restrictedToAppointments
                ? ($promo->relationLoaded('appointments')
                    ? $promo->appointments->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                    : $promo->appointments()->pluck('appointments.id')->map(fn ($id) => (int) $id)->values()->all())
                : [],
            'restricted_to_branches' => $restrictedToBranches,
            'allowed_branch_ids' => $restrictedToBranches
                ? ($promo->relationLoaded('branches')
                    ? $promo->branches->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
                    : $promo->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->values()->all())
                : [],
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
