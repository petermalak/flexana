<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Services\ServiceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ServiceRepository implements ServiceRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(ServiceModel::query())
            ->allowedFilters([
                'name',
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['name', 'price', 'position']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $builder->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ServiceModel
    {
        return ServiceModel::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $payload): ServiceModel
    {
        $model = ServiceModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        return $model;
    }

    public function update(ServiceModel $service, array $payload): ServiceModel
    {
        $service->fill($payload)->save();

        return $service;
    }

    public function delete(ServiceModel $service): void
    {
        $service->delete();
    }
}

