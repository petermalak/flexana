<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Models\ApiKey;
use App\Support\BranchSettings;
use App\Support\PosTransactionMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * POS / mall integration: paid transactions for a date range, scoped to one branch.
 */
class PosTransactionsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $branchId = $apiKey?->branch_id !== null ? (int) $apiKey->branch_id : null;
        if ($branchId === null || $branchId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'This API user is not assigned to a branch.',
            ], 403);
        }

        $from = (string) $validator->validated()['from'];
        $to = (string) $validator->validated()['to'];
        $bizTz = (string) config('app.business_timezone');
        $fromAt = Carbon::createFromFormat('Y-m-d', $from, $bizTz)->startOfDay();
        $toAt = Carbon::createFromFormat('Y-m-d', $to, $bizTz)->endOfDay();

        $sales = $this->salesPaymentsQuery($fromAt, $toAt, $branchId)->get();
        $transactions = $sales
            ->map(fn (PaymentModel $payment) => PosTransactionMapper::fromPayment($payment))
            ->values()
            ->all();

        $returns = $this->returnPaymentsQuery($fromAt, $toAt, $branchId)->get();
        foreach ($returns as $payment) {
            $transactions[] = PosTransactionMapper::fromPayment($payment, asReturn: true);
        }

        usort($transactions, function (array $a, array $b): int {
            return strcmp((string) ($a['transactionDateTime'] ?? ''), (string) ($b['transactionDateTime'] ?? ''));
        });

        return response()->json([
            'success' => true,
            'from' => $from,
            'to' => $to,
            'transactions' => array_values($transactions),
        ]);
    }

    private function salesPaymentsQuery(Carbon $fromAt, Carbon $toAt, int $branchId)
    {
        $query = PaymentModel::query()
            ->with([
                'booking',
                'promoCode',
            ])
            ->whereIn('status', ['paid', 'completed'])
            ->whereBetween('paid_at', [$fromAt, $toAt])
            ->orderBy('paid_at');

        $this->applyBranchFilter($query, $branchId);

        return $query;
    }

    private function returnPaymentsQuery(Carbon $fromAt, Carbon $toAt, int $branchId)
    {
        $query = PaymentModel::query()
            ->with([
                'booking',
                'promoCode',
            ])
            ->whereIn('status', ['paid', 'completed', 'refunded'])
            ->whereHas('booking', function ($bookingQuery) use ($fromAt, $toAt): void {
                $bookingQuery
                    ->where('status', 'cancelled')
                    ->whereNotNull('cancelled_at')
                    ->whereBetween('cancelled_at', [$fromAt, $toAt]);
            })
            ->orderBy('paid_at');

        $this->applyBranchFilter($query, $branchId);

        return $query;
    }

    private function applyBranchFilter($query, int $branchId): void
    {
        $defaultBranchId = BranchSettings::defaultBranchId();

        $query->whereHas('booking.appointment', function ($appointmentQuery) use ($branchId, $defaultBranchId): void {
            $appointmentQuery->where(function ($inner) use ($branchId, $defaultBranchId): void {
                $inner->where('branch_id', $branchId);

                if ($defaultBranchId !== null && $defaultBranchId === $branchId) {
                    $inner->orWhereNull('branch_id');
                }
            });
        });
    }
}
