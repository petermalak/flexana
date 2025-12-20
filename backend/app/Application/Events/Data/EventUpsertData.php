<?php

namespace App\Application\Events\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\NumericType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Timezone;
use Spatie\LaravelData\Data;

final class EventUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $name,
        #[StringType]
        public ?string $slug,
        #[StringType]
        public ?string $category,
        #[StringType]
        public string $status,
        #[Timezone]
        public string $timezone,
        #[StringType]
        public ?string $description,
        #[IntegerType]
        public ?int $capacity,
        #[NumericType]
        public float $price,
        #[NumericType]
        public float $depositAmount,
        #[BooleanType]
        public bool $allowWaitlist,
        #[ArrayType]
        public ?array $recurrence,
        #[ArrayType]
        public ?array $meta,
        #[StringType]
        public ?string $instructorUuid,
        #[StringType]
        public ?string $classTypeUuid,
        #[ArrayType]
        public ?array $serviceUuids,
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'alpha_dash', 'max:255'],
            'category' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:draft,scheduled,published,archived'],
            'timezone' => ['required', 'timezone'],
            'description' => ['nullable', 'string'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'depositAmount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'allowWaitlist' => ['boolean'],
            'recurrence' => ['nullable', 'array'],
            'meta' => ['nullable', 'array'],
            'instructorUuid' => ['nullable', 'uuid'],
            'classTypeUuid' => ['nullable', 'uuid'],
            'serviceUuids' => ['nullable', 'array'],
            'serviceUuids.*' => ['uuid'],
        ];
    }
}

