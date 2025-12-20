<?php

namespace App\Application\Staff\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Data;

final class StaffUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $name,
        #[Email]
        public ?string $email,
        #[StringType]
        public ?string $phone,
        #[StringType]
        public string $role,
        #[StringType]
        public ?string $colorHex,
        #[Timezone]
        public string $timezone,
        #[ArrayType]
        public ?array $skills,
        #[BooleanType]
        public bool $isActive,
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'max:50'],
            'colorHex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'timezone' => ['required', 'timezone'],
            'skills' => ['nullable', 'array'],
            'isActive' => ['boolean'],
        ];
    }
}

