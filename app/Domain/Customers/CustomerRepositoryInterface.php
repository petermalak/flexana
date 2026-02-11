<?php

namespace App\Domain\Customers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Customer;

    public function create(array $payload): Customer;

    public function update(Customer $customer, array $payload): Customer;
}

