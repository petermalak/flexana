<?php

namespace App\Application\ClassTypes\Data;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class ClassTypeUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $name,
        #[StringType]
        public ?string $slug,
        #[StringType]
        public ?string $description,
        #[StringType]
        public ?string $colorHex,
        #[BooleanType]
        public bool $isActive,
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255', 'unique:class_types,slug'],
            'description' => ['nullable', 'string'],
            'colorHex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'isActive' => ['boolean'],
        ];
    }
}

