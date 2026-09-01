<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use Illuminate\Support\Carbon;

final class PosTransactionMapper
{
    /**
     * @return array<string, mixed>
     */
    public static function fromPayment(PaymentModel $payment, bool $asReturn = false): array
    {
        $payment->loadMissing([
            'booking',
            'promoCode',
        ]);

        $booking = $payment->booking;
        $amounts = self::amountBreakdown($payment);
        $sign = $asReturn ? -1 : 1;
        $transactionAt = $asReturn
            ? ($booking?->cancelled_at ?? $payment->paid_at ?? $payment->created_at)
            : ($payment->paid_at ?? $payment->created_at);

        $invoiceSuffix = $asReturn ? '-R' : '';

        return [
            'invoiceNumber' => self::invoiceNumber($payment).$invoiceSuffix,
            'transactionDateTime' => self::formatDateTime($transactionAt),
            'subtotal' => self::signedAmount($amounts['subtotal'], $sign),
            'taxAmount' => self::signedAmount($amounts['taxAmount'], $sign),
            'serviceCharge' => self::signedAmount($amounts['serviceCharge'], $sign),
            'discountAmount' => self::signedAmount($amounts['discountAmount'], $sign),
            'invoiceTotal' => self::signedAmount($amounts['invoiceTotal'], $sign),
        ];
    }

    /**
     * @return array{subtotal: float, taxAmount: float, serviceCharge: float, discountAmount: float, invoiceTotal: float}
     */
    public static function amountBreakdown(PaymentModel $payment): array
    {
        $invoiceTotal = round((float) $payment->amount, 2);
        $taxAmount = round((float) config('pos.default_tax_amount', 0), 2);
        $serviceCharge = round((float) config('pos.default_service_charge', 0), 2);

        $promo = $payment->promoCode;
        $discountAmount = 0.0;
        $subtotal = $invoiceTotal;

        if ($promo && (float) $promo->percent_discount > 0) {
            $rate = (float) $promo->percent_discount / 100;
            if ($rate < 1) {
                $subtotal = round($invoiceTotal / (1 - $rate), 2);
                $discountAmount = round(max(0, $subtotal - $invoiceTotal), 2);
            }
        }

        return [
            'subtotal' => $subtotal,
            'taxAmount' => $taxAmount,
            'serviceCharge' => $serviceCharge,
            'discountAmount' => $discountAmount,
            'invoiceTotal' => $invoiceTotal,
        ];
    }

    public static function invoiceNumber(PaymentModel $payment): string
    {
        $prefix = (string) config('pos.invoice_prefix', 'FLX');

        return $prefix.'-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT);
    }

    private static function signedAmount(float $amount, int $sign): float
    {
        return round($amount * $sign, 2);
    }

    private static function formatDateTime(Carbon|\DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ApiDateTime::toBusinessIso8601($value);
    }
}
