<?php

namespace App\Application\Bookings\Data;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\NumericType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class BookingCreateData extends Data
{
    public function __construct(
        #[StringType]
        public string $eventUuid,
        #[StringType]
        public ?string $eventInstanceUuid,
        #[StringType]
        public string $customerUuid,
        #[IntegerType]
        public int $partySize,
        #[NumericType]
        public float $totalAmount,
        #[NumericType]
        public float $depositAmount,
        #[NumericType]
        public float $balanceAmount,
        #[StringType]
        public string $currency,
        #[StringType]
        public string $channel,
        #[ArrayType]
        public ?array $answers,
        #[StringType]
        public ?string $notes,
        #[StringType]
        public ?string $packageUuid,
        #[StringType]
        public ?string $serviceUuid,
    ) {
    }

    public static function rules(?\Spatie\LaravelData\Support\Validation\ValidationContext $context = null): array
    {
        return [
            'eventUuid' => ['required', 'uuid'],
            'eventInstanceUuid' => ['nullable', 'uuid'],
            'customerUuid' => ['required', 'uuid'],
            'partySize' => ['required', 'integer', 'min:1', 'max:100'],
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'depositAmount' => ['nullable', 'numeric', 'min:0'],
            'balanceAmount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'channel' => ['required', 'in:mobile,web,admin'],
            'answers' => ['nullable', 'array'],
            'notes' => ['nullable', 'string'],
            'packageUuid' => ['nullable', 'uuid'],
            'serviceUuid' => ['nullable', 'uuid'],
        ];
    }
}

