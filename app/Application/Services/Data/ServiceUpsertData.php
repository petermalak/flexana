<?php

namespace App\Application\Services\Data;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\NumericType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class ServiceUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $name,
        #[StringType]
        public ?string $description,
        #[IntegerType]
        public int $duration,
        #[NumericType]
        public float $price,
        #[IntegerType]
        public int $minCapacity,
        #[IntegerType]
        public int $maxCapacity,
        #[StringType]
        public ?string $colorHex,
        #[StringType]
        public string $status,
    ) {
    }

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration' => ['required', 'integer', 'min:1'],
            'price' => ['required', 'numeric', 'min:0'],
            'minCapacity' => ['required', 'integer', 'min:1'],
            'maxCapacity' => ['required', 'integer', 'min:1'],
            'colorHex' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', 'in:visible,hidden'],
        ];
    }
}

