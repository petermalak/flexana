<?php

namespace App\Domain\Bookings;

use App\Domain\Bookings\Enums\BookingStatus;
use Carbon\CarbonImmutable;

final class Booking
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $eventUuid,
        public readonly ?string $eventInstanceUuid,
        public readonly string $customerUuid,
        public BookingStatus $status,
        public string $paymentStatus,
        public int $partySize,
        public float $totalAmount,
        public float $depositAmount,
        public float $balanceAmount,
        public string $currency,
        public string $channel,
        public ?array $answers,
        public ?string $notes,
        public CarbonImmutable $bookedAt,
        public ?CarbonImmutable $cancelledAt,
    ) {
    }
}

