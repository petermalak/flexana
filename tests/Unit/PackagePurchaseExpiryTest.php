<?php

namespace Tests\Unit;

use App\Support\PackagePurchaseExpiry;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PackagePurchaseExpiryTest extends TestCase
{
    public function test_covers_session_date_when_session_is_on_expiry_day(): void
    {
        $bizTz = 'Africa/Cairo';
        $expiresAt = Carbon::parse('2026-06-20', $bizTz)->startOfDay();
        $sessionDate = Carbon::parse('2026-06-20 18:00:00', $bizTz);

        $this->assertTrue(PackagePurchaseExpiry::coversSessionDate($expiresAt, $sessionDate, $bizTz));
    }

    public function test_covers_session_date_when_session_is_before_expiry(): void
    {
        $bizTz = 'Africa/Cairo';
        $expiresAt = Carbon::parse('2026-06-20', $bizTz)->startOfDay();
        $sessionDate = Carbon::parse('2026-06-15 10:00:00', $bizTz);

        $this->assertTrue(PackagePurchaseExpiry::coversSessionDate($expiresAt, $sessionDate, $bizTz));
    }

    public function test_does_not_cover_session_date_after_expiry(): void
    {
        $bizTz = 'Africa/Cairo';
        $expiresAt = Carbon::parse('2026-06-20', $bizTz)->startOfDay();
        $sessionDate = Carbon::parse('2026-06-30 08:30:00', $bizTz);

        $this->assertFalse(PackagePurchaseExpiry::coversSessionDate($expiresAt, $sessionDate, $bizTz));
    }

    public function test_covers_session_date_when_expiry_is_unknown(): void
    {
        $bizTz = 'Africa/Cairo';
        $sessionDate = Carbon::parse('2026-12-01 10:00:00', $bizTz);

        $this->assertTrue(PackagePurchaseExpiry::coversSessionDate(null, $sessionDate, $bizTz));
    }
}
