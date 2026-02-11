<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Staff\StaffRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

final class StaffRepository implements StaffRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(StaffModel::query())
            ->allowedFilters([
                'name',
                'email',
                AllowedFilter::exact('role'),
                AllowedFilter::exact('is_active'),
            ])
            ->allowedSorts(['name', 'email', 'created_at']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $builder->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?StaffModel
    {
        return StaffModel::query()
            ->where('uuid', $uuid)
            ->first();
    }

    public function create(array $payload): StaffModel
    {
        $model = StaffModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        return $model;
    }

    public function update(StaffModel $staff, array $payload): StaffModel
    {
        $staff->fill($payload)->save();

        return $staff;
    }

    public function delete(StaffModel $staff): void
    {
        $staff->delete();
    }
}

