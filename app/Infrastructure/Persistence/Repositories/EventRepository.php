<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Event;
use App\Domain\Events\EventRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EventModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class EventRepository implements EventRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(EventModel::query())
            ->allowedFilters([
                'name',
                'category',
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['name', 'published_at', 'status', 'created_at']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $builder
            ->paginate($perPage)
            ->through(fn (EventModel $model) => $this->toDomain($model));
    }

    public function findByUuid(string $uuid): ?Event
    {
        $model = EventModel::query()->where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function create(array $payload): Event
    {
        $model = EventModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
            'slug' => $payload['slug'] ?? Str::slug($payload['name'] . '-' . Str::lower(Str::random(6))),
        ]));

        return $this->toDomain($model);
    }

    public function update(Event $event, array $payload): Event
    {
        $model = EventModel::query()->where('uuid', $event->uuid)->first();

        if (! $model) {
            throw new ModelNotFoundException('Event not found');
        }

        $model->fill($payload)->save();

        return $this->toDomain($model);
    }

    public function delete(Event $event): void
    {
        EventModel::query()
            ->where('uuid', $event->uuid)
            ->delete();
    }

    private function toDomain(EventModel $model): Event
    {
        return new Event(
            id: $model->id,
            uuid: $model->uuid,
            slug: $model->slug,
            name: $model->name,
            category: $model->category,
            status: EventStatus::from($model->status),
            timezone: $model->timezone,
            description: $model->description,
            capacity: $model->capacity,
            price: (float) $model->price,
            depositAmount: (float) $model->deposit_amount,
            allowWaitlist: (bool) $model->allow_waitlist,
            recurrence: $model->recurrence,
            meta: $model->meta,
            publishedAt: $model->published_at?->toImmutable(),
        );
    }
}

