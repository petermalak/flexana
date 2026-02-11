<?php

namespace App\Domain\Customers;

use Carbon\CarbonImmutable;

final class Customer
{
    public function __construct(
        public readonly int $id,
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

    public function fullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }
}

