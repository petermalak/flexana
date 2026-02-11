<?php

namespace App\Domain\Events;

use Carbon\CarbonImmutable;

final class EventInstance
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $eventUuid,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public ?int $capacity,
        public ?string $location,
        public string $status,
        public ?array $resources,
        public ?CarbonImmutable $bookingOpenDate,
        public ?CarbonImmutable $bookingCloseDate,
        public ?string $instructorUuid,
    ) {
    }
}

