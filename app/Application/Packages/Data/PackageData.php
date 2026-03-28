<?php

namespace App\Application\Packages\Data;

use Spatie\LaravelData\Data;

final class PackageData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?array $classType,
        public readonly string $title,
        public readonly ?string $description,
        public readonly int $sortOrder,
        public readonly int $totalSessions,
        public readonly int $usedSessions,
        public readonly float $discount,
        public readonly float $price,
        public readonly ?string $expiry,
        public readonly string $status,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }
}

