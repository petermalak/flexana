<?php

namespace App\Application\Events\Data;

use Spatie\LaravelData\Data;

final class EventInstanceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $eventUuid,
        public readonly string $startsAt,
        public readonly string $endsAt,
        public readonly ?int $capacity,
        public readonly ?string $location,
        public readonly string $status,
        public readonly ?array $resources,
        public readonly ?string $bookingOpenDate,
        public readonly ?string $bookingCloseDate,
        public readonly ?string $instructorUuid,
    ) {
    }
}

