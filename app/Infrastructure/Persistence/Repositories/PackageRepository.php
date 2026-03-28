<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Packages\PackageRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class PackageRepository implements PackageRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(PackageModel::query()->with(['classType']))
            ->allowedFilters([
                'title',
                AllowedFilter::exact('status'),
                AllowedFilter::exact('class_type_id'),
            ])
            ->allowedSorts(['sort_order', 'title', 'price', 'created_at']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $builder->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?PackageModel
    {
        return PackageModel::query()
            ->where('uuid', $uuid)
            ->with(['classType', 'services'])
            ->first();
    }

    public function create(array $payload): PackageModel
    {
        $model = PackageModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        if (isset($payload['service_ids'])) {
            $model->services()->attach($payload['service_ids']);
        }

        return $model->load(['classType', 'services']);
    }

    public function update(PackageModel $package, array $payload): PackageModel
    {
        $package->fill($payload)->save();

        if (isset($payload['service_ids'])) {
            $package->services()->sync($payload['service_ids']);
        }

        return $package->load(['classType', 'services']);
    }

    public function delete(PackageModel $package): void
    {
        $package->delete();
    }

    public function incrementUsedSessions(PackageModel $package, int $count = 1): void
    {
        $package->increment('used_sessions', $count);
    }
}

