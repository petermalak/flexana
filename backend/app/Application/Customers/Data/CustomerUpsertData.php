<?php

namespace App\Application\Customers\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class CustomerUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $firstName,
        #[StringType]
        public ?string $lastName,
        #[StringType]
        public ?string $email,
        #[StringType]
        public ?string $phone,
        #[StringType]
        public string $timezone,
        #[ArrayType]
        public ?array $preferences,
        #[StringType]
        public ?string $source,
        #[StringType]
        public ?string $notes,
    ) {
    }

    public static function rules(): array
    {
        return [
            'firstName' => ['required', 'string', 'max:120'],
            'lastName' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'timezone'],
            'preferences' => ['nullable', 'array'],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

