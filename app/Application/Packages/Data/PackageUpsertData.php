<?php

namespace App\Application\Packages\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\NumericType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class PackageUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public ?string $classTypeUuid,
        #[StringType]
        public string $title,
        #[StringType]
        public ?string $description,
        #[IntegerType]
        public int $totalSessions,
        #[NumericType]
        public float $discount,
        #[NumericType]
        public float $price,
        #[Date]
        public ?string $expiry,
        #[StringType]
        public string $status,
        #[ArrayType]
        public ?array $serviceUuids,
    ) {
    }

    public static function rules(): array
    {
        return [
            'classTypeUuid' => ['nullable', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'totalSessions' => ['required', 'integer', 'min:1'],
            'discount' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'expiry' => ['nullable', 'date', 'after:today'],
            'status' => ['required', 'in:active,inactive'],
            'serviceUuids' => ['nullable', 'array'],
            'serviceUuids.*' => ['uuid'],
        ];
    }
}

