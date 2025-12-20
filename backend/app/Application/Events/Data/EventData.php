<?php

namespace App\Application\Events\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class EventData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly ?string $category,
        public readonly string $status,
        public readonly string $timezone,
        public readonly ?string $description,
        public readonly ?int $capacity,
        public readonly float $price,
        public readonly float $depositAmount,
        public readonly bool $allowWaitlist,
        public readonly ?array $recurrence,
        public readonly ?array $meta,
        public readonly ?CarbonImmutable $publishedAt,
        public readonly ?array $instructor,
        public readonly ?array $classType,
        public readonly array $services,
    ) {
    }
}

