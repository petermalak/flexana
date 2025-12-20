<?php

namespace App\Application\Events\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class EventInstanceUpsertData extends Data
{
    public function __construct(
        #[StringType]
        public string $eventUuid,
        public string $startsAt,
        public string $endsAt,
        #[IntegerType]
        public ?int $capacity,
        #[StringType]
        public ?string $location,
        #[StringType]
        public string $status,
        public ?array $resources,
        public ?string $bookingOpenDate,
        public ?string $bookingCloseDate,
        #[StringType]
        public ?string $instructorUuid,
    ) {
    }

    public static function rules(): array
    {
        return [
            'eventUuid' => ['required', 'uuid'],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:scheduled,cancelled,completed'],
            'resources' => ['nullable', 'array'],
            'bookingOpenDate' => ['nullable', 'date'],
            'bookingCloseDate' => ['nullable', 'date', 'after:bookingOpenDate'],
            'instructorUuid' => ['nullable', 'uuid'],
        ];
    }

    public function toArray(): array
    {
        return [
            'starts_at' => Carbon::parse($this->startsAt),
            'ends_at' => Carbon::parse($this->endsAt),
            'capacity' => $this->capacity,
            'location' => $this->location,
            'status' => $this->status,
            'resources' => $this->resources,
            'booking_open_date' => $this->bookingOpenDate ? Carbon::parse($this->bookingOpenDate) : null,
            'booking_close_date' => $this->bookingCloseDate ? Carbon::parse($this->bookingCloseDate) : null,
        ];
    }
}

