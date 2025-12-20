<?php

namespace App\Domain\Events;

use App\Domain\Events\Enums\EventStatus;
use Carbon\CarbonImmutable;

final class Event
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public string $slug,
        public string $name,
        public ?string $category,
        public EventStatus $status,
        public string $timezone,
        public ?string $description,
        public ?int $capacity,
        public float $price,
        public float $depositAmount,
        public bool $allowWaitlist,
        public ?array $recurrence,
        public ?array $meta,
        public ?CarbonImmutable $publishedAt,
    ) {
    }
}

