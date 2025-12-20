<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\ClassTypes\ClassTypeRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ClassTypeModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class ClassTypeRepository implements ClassTypeRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(ClassTypeModel::query())
            ->allowedFilters([
                'name',
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'created_at']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $builder->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?ClassTypeModel
    {
        return ClassTypeModel::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $payload): ClassTypeModel
    {
        $model = ClassTypeModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        return $model;
    }

    public function update(ClassTypeModel $classType, array $payload): ClassTypeModel
    {
        $classType->fill($payload)->save();

        return $classType;
    }

    public function delete(ClassTypeModel $classType): void
    {
        $classType->delete();
    }
}

