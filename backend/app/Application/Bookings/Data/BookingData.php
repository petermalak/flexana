<?php

namespace App\Application\Bookings\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class BookingData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $eventUuid,
        public readonly ?string $eventInstanceUuid,
        public readonly string $customerUuid,
        public readonly string $status,
        public readonly string $paymentStatus,
        public readonly int $partySize,
        public readonly float $totalAmount,
        public readonly float $depositAmount,
        public readonly float $balanceAmount,
        public readonly string $currency,
        public readonly string $channel,
        public readonly ?array $answers,
        public readonly ?string $notes,
        public readonly CarbonImmutable $bookedAt,
        public readonly ?CarbonImmutable $cancelledAt,
    ) {
    }
}

