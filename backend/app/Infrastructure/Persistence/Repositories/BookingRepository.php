<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Bookings\Booking;
use App\Domain\Bookings\BookingRepositoryInterface;
use App\Domain\Bookings\Enums\BookingStatus;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class BookingRepository implements BookingRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(BookingModel::query()->with(['event', 'customer']))
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('payment_status'),
                AllowedFilter::callback('date_between', function ($query, $value) {
                    [$start, $end] = $value;
                    $query->whereBetween('booked_at', [$start, $end]);
                }),
            ])
            ->allowedSorts(['booked_at', 'status', 'total_amount']);

        if ($search = data_get($filters, 'search')) {
            $builder->whereHas('customer', function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $builder
            ->paginate($perPage)
            ->through(fn (BookingModel $model) => $this->toDomain($model));
    }

    public function create(array $payload): Booking
    {
        $model = BookingModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
            'booked_at' => $payload['booked_at'] ?? now(),
        ]));

        return $this->toDomain($model);
    }

    public function update(Booking $booking, array $payload): Booking
    {
        $model = BookingModel::query()->where('uuid', $booking->uuid)->first();

        if (! $model) {
            throw new ModelNotFoundException('Booking not found');
        }

        $model->fill($payload)->save();

        return $this->toDomain($model);
    }

    public function findByUuid(string $uuid): ?Booking
    {
        $model = BookingModel::query()->where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function countByEventInstance(int $eventInstanceId): int
    {
        return BookingModel::query()
            ->where('event_instance_id', $eventInstanceId)
            ->where('status', '!=', 'cancelled')
            ->sum('party_size');
    }

    public function findByEventInstance(int $eventInstanceId): array
    {
        return BookingModel::query()
            ->where('event_instance_id', $eventInstanceId)
            ->get()
            ->map(fn (BookingModel $model) => $this->toDomain($model))
            ->toArray();
    }

    public function findByPackage(string $packageUuid): array
    {
        return BookingModel::query()
            ->whereHas('package', function ($query) use ($packageUuid) {
                $query->where('uuid', $packageUuid);
            })
            ->get()
            ->map(fn (BookingModel $model) => $this->toDomain($model))
            ->toArray();
    }

    public function findByCustomer(string $customerUuid): array
    {
        return BookingModel::query()
            ->whereHas('customer', function ($query) use ($customerUuid) {
                $query->where('uuid', $customerUuid);
            })
            ->get()
            ->map(fn (BookingModel $model) => $this->toDomain($model))
            ->toArray();
    }

    private function toDomain(BookingModel $model): Booking
    {
        return new Booking(
            id: $model->id,
            uuid: $model->uuid,
            eventUuid: $model->event?->uuid ?? '',
            eventInstanceUuid: $model->eventInstance?->uuid,
            customerUuid: $model->customer?->uuid ?? '',
            status: BookingStatus::from($model->status),
            paymentStatus: $model->payment_status,
            partySize: $model->party_size,
            totalAmount: (float) $model->total_amount,
            depositAmount: (float) $model->deposit_amount,
            balanceAmount: (float) $model->balance_amount,
            currency: $model->currency,
            channel: $model->channel,
            answers: $model->answers,
            notes: $model->notes,
            bookedAt: $model->booked_at?->toImmutable() ?? now()->toImmutable(),
            cancelledAt: $model->cancelled_at?->toImmutable(),
        );
    }
}

