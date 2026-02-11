<?php

namespace App\Application\Services\Data;

use Spatie\LaravelData\Data;

final class ServiceData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $description,
        public readonly int $duration,
        public readonly float $price,
        public readonly int $minCapacity,
        public readonly int $maxCapacity,
        public readonly ?string $colorHex,
        public readonly string $status,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }
}

