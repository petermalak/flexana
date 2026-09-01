<?php

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Support\PosTransactionMapper;
use Tests\TestCase;

class PosTransactionMapperTest extends TestCase
{
    public function test_maps_sale_with_promo_discount(): void
    {
        $promo = new PromoCodeModel([
            'percent_discount' => 10,
        ]);

        $payment = new PaymentModel([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'amount' => 360,
            'currency' => 'EGP',
            'provider' => 'paymob',
            'transaction_id' => 'TX-123',
            'paid_at' => now(),
        ]);
        $payment->exists = true;
        $payment->id = 42;
        $payment->setRelation('promoCode', $promo);
        $payment->setRelation('booking', null);

        $mapped = PosTransactionMapper::fromPayment($payment);

        $this->assertSame('FLX-00000042', $mapped['invoiceNumber']);
        $this->assertSame(400.0, $mapped['subtotal']);
        $this->assertSame(40.0, $mapped['discountAmount']);
        $this->assertSame(360.0, $mapped['invoiceTotal']);
        $this->assertArrayNotHasKey('isReturn', $mapped);
    }

    public function test_maps_return_as_negative_amounts(): void
    {
        $payment = new PaymentModel([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'amount' => 400,
            'currency' => 'EGP',
            'provider' => 'paymob',
            'paid_at' => now(),
        ]);
        $payment->exists = true;
        $payment->id = 7;
        $payment->setRelation('promoCode', null);
        $payment->setRelation('booking', null);

        $mapped = PosTransactionMapper::fromPayment($payment, asReturn: true);

        $this->assertSame('FLX-00000007-R', $mapped['invoiceNumber']);
        $this->assertSame(-400.0, $mapped['invoiceTotal']);
        $this->assertArrayNotHasKey('isReturn', $mapped);
    }
}
