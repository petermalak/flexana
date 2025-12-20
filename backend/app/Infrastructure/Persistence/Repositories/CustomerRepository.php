<?php

namespace App\Infrastructure\Persistence\Repositories;

use App\Domain\Customers\Customer;
use App\Domain\Customers\CustomerRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\CustomerModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\QueryBuilder;

final class CustomerRepository implements CustomerRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $builder = QueryBuilder::for(CustomerModel::query())
            ->allowedFilters(['first_name', 'last_name', 'email', 'source'])
            ->allowedSorts(['first_name', 'last_name', 'last_seen_at']);

        if ($search = data_get($filters, 'search')) {
            $builder->where(function ($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $builder
            ->paginate($perPage)
            ->through(fn (CustomerModel $model) => $this->toDomain($model));
    }

    public function findByUuid(string $uuid): ?Customer
    {
        $model = CustomerModel::query()->where('uuid', $uuid)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function create(array $payload): Customer
    {
        $model = CustomerModel::query()->create(array_merge($payload, [
            'uuid' => Str::uuid()->toString(),
        ]));

        return $this->toDomain($model);
    }

    public function update(Customer $customer, array $payload): Customer
    {
        $model = CustomerModel::query()->where('uuid', $customer->uuid)->first();

        if (! $model) {
            throw new ModelNotFoundException('Customer not found');
        }

        $model->fill($payload)->save();

        return $this->toDomain($model);
    }

    private function toDomain(CustomerModel $model): Customer
    {
        return new Customer(
            id: $model->id,
            uuid: $model->uuid,
            firstName: $model->first_name,
            lastName: $model->last_name,
            email: $model->email,
            phone: $model->phone,
            timezone: $model->timezone,
            preferences: $model->preferences,
            source: $model->source,
            notes: $model->notes,
            lastSeenAt: $model->last_seen_at?->toImmutable(),
        );
    }
}

