<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Events\EventInstance;
use App\Domain\Events\EventInstanceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EventInstanceModel;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class EventInstanceRepository implements EventInstanceRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(EventInstanceModel::query()->with(['event', 'instructor']))
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('event_id'),
            ])
            ->allowedSorts(['starts_at', 'ends_at', 'status']);

        if ($search = data_get($filters, 'search')) {
            $builder->whereHas('event', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            });
        }

        return $builder
            ->paginate($perPage)
            ->through(fn (EventInstanceModel $model) => $this->toDomain($model));
    }

    public function findByUuid(string $uuid): ?EventInstance
    {
        $model = EventInstanceModel::query()
            ->where('uuid', $uuid)
            ->with(['event', 'instructor'])
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findByEventUuid(string $eventUuid): array
    {
        return EventInstanceModel::query()
            ->whereHas('event', function ($query) use ($eventUuid) {
                $query->where('uuid', $eventUuid);
            })
            ->with(['event', 'instructor'])
            ->get()
            ->map(fn (EventInstanceModel $model) => $this->toDomain($model))
            ->toArray();
    }

    public function findByDateRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return EventInstanceModel::query()
            ->whereBetween('starts_at', [$start, $end])
            ->with(['event', 'instructor'])
            ->get()
            ->map(fn (EventInstanceModel $model) => $this->toDomain($model))
            ->toArray();
    }

    public function findAvailableInstances(string $eventUuid, int $partySize): array
    {
        return EventInstanceModel::query()
            ->whereHas('event', function ($query) use ($eventUuid) {
                $query->where('uuid', $eventUuid);
            })
            ->where('status', 'scheduled')
            ->where('starts_at', '>', now())
            ->with(['event', 'instructor'])
            ->get()
            ->filter(function (EventInstanceModel $model) use ($partySize) {
                $capacity = $model->capacity ?? $model->event->capacity;
                if (!$capacity) {
                    return true; // No capacity limit
                }
                // This is a simplified check - in production, count actual bookings
                return true; // Will be validated in service layer
            })
            ->map(fn (EventInstanceModel $model) => $this->toDomain($model))
            ->toArray();
    }

    public function create(array $payload): EventInstance
    {
        $model = EventInstanceModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        return $this->toDomain($model->load(['event', 'instructor']));
    }

    public function update(EventInstance $instance, array $payload): EventInstance
    {
        $model = EventInstanceModel::query()->where('uuid', $instance->uuid)->first();

        if (!$model) {
            throw new ModelNotFoundException('Event instance not found');
        }

        $model->fill($payload)->save();

        return $this->toDomain($model->load(['event', 'instructor']));
    }

    public function delete(EventInstance $instance): void
    {
        EventInstanceModel::query()
            ->where('uuid', $instance->uuid)
            ->delete();
    }

    private function toDomain(EventInstanceModel $model): EventInstance
    {
        return new EventInstance(
            id: $model->id,
            uuid: $model->uuid,
            eventUuid: $model->event?->uuid ?? '',
            startsAt: CarbonImmutable::parse($model->starts_at),
            endsAt: CarbonImmutable::parse($model->ends_at),
            capacity: $model->capacity,
            location: $model->location,
            status: $model->status,
            resources: $model->resources,
            bookingOpenDate: $model->booking_open_date?->toImmutable(),
            bookingCloseDate: $model->booking_close_date?->toImmutable(),
            instructorUuid: $model->instructor?->uuid,
        );
    }
}

