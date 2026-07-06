<?php

namespace Tests\Unit;

use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Support\PackagePurchaseLifecycle;
use PHPUnit\Framework\TestCase;

class PackagePurchaseLifecycleTest extends TestCase
{
    public function test_expires_active_purchase_when_remaining_sessions_reach_zero(): void
    {
        $purchase = new TestableCustomerPackagePurchase([
            'remaining_sessions' => 0,
            'status' => 'active',
        ]);

        PackagePurchaseLifecycle::afterSessionsConsumed($purchase);

        $this->assertSame('expired', $purchase->status);
        $this->assertSame(0, $purchase->remaining_sessions);
        $this->assertTrue($purchase->wasUpdated);
    }

    public function test_keeps_active_purchase_when_sessions_remain(): void
    {
        $purchase = new TestableCustomerPackagePurchase([
            'remaining_sessions' => 2,
            'status' => 'active',
        ]);

        PackagePurchaseLifecycle::afterSessionsConsumed($purchase);

        $this->assertSame('active', $purchase->status);
        $this->assertSame(2, $purchase->remaining_sessions);
        $this->assertFalse($purchase->wasUpdated);
    }

    public function test_does_not_update_already_inactive_purchase(): void
    {
        $purchase = new TestableCustomerPackagePurchase([
            'remaining_sessions' => 0,
            'status' => 'expired',
        ]);

        PackagePurchaseLifecycle::afterSessionsConsumed($purchase);

        $this->assertSame('expired', $purchase->status);
        $this->assertFalse($purchase->wasUpdated);
    }
}

/**
 * @property int $remaining_sessions
 * @property string $status
 */
final class TestableCustomerPackagePurchase extends CustomerPackagePurchaseModel
{
    public bool $wasUpdated = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->exists = true;
    }

    public function refresh(): static
    {
        return $this;
    }

    public function update(array $attributes = [], array $options = [])
    {
        $this->wasUpdated = true;
        $this->fill($attributes);

        return true;
    }
}
