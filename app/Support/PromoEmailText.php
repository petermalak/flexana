<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;

final class PromoEmailText
{
    /**
     * Extra lines for plain-text emails when a promo was applied.
     */
    public static function appliedSection(
        ?PromoCodeModel $promo,
        float $amountBeforeDiscount,
        float $amountAfterDiscount,
        string $currency = 'USD',
    ): string {
        if ($promo === null) {
            return '';
        }

        $code = $promo->code;
        $pct = (float) $promo->percent_discount;
        $pctLabel = fmod($pct, 1.0) === 0.0 ? (string) (int) $pct : rtrim(rtrim(number_format($pct, 2), '0'), '.');
        $before = number_format(max(0, $amountBeforeDiscount), 2);
        $after = number_format(max(0, $amountAfterDiscount), 2);
        $saved = number_format(max(0, $amountBeforeDiscount - $amountAfterDiscount), 2);

        return "\n* Promo code: {$code}\n"
            . "* Discount: {$pctLabel}% off (you save {$currency} {$saved})\n"
            . "* Price before discount: {$currency} {$before}\n"
            . "* Amount charged: {$currency} {$after}\n\n";
    }
}
