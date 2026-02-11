<?php

namespace App\Application\ClassTypes\Data;

use Spatie\LaravelData\Data;

final class ClassTypeData extends Data
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?string $colorHex,
        public readonly bool $isActive,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {
    }
}

