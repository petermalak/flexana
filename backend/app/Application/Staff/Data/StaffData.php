<?php

namespace App\Application\Staff\Data;

use Spatie\LaravelData\Data;

final class StaffData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly ?string $email,
        public readonly ?string $phone,
        public readonly string $role,
        public readonly ?string $colorHex,
        public readonly string $timezone,
        public readonly ?array $skills,
        public readonly bool $isActive,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }
}

