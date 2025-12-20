<?php

namespace App\Application\Customers\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

final class CustomerData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $timezone,
        public readonly ?array $preferences,
        public readonly ?string $source,
        public readonly ?string $notes,
        public readonly ?CarbonImmutable $lastSeenAt,
    ) {
    }
}

