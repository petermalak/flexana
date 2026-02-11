<?php

namespace App\Application\Customers\Services;

use App\Application\Customers\Data\CustomerData;
use App\Application\Customers\Data\CustomerUpsertData;
use App\Domain\Customers\Customer;
use App\Domain\Customers\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->customers->paginate($filters, $perPage)
            ->through(fn (Customer $customer) => CustomerData::from($this->mapToArray($customer)));
    }

    public function create(CustomerUpsertData $payload): CustomerData
    {
        $customer = $this->customers->create($payload->toArray());

        return CustomerData::from($this->mapToArray($customer));
    }

    public function update(string $uuid, CustomerUpsertData $payload): CustomerData
    {
        $customer = $this->customers->findByUuid($uuid);

        abort_if(! $customer, 404, 'Customer not found.');

        $updated = $this->customers->update($customer, $payload->toArray());

        return CustomerData::from($this->mapToArray($updated));
    }

    private function mapToArray(Customer $customer): array
    {
        return [
            'uuid' => $customer->uuid,
            'firstName' => $customer->firstName,
            'lastName' => $customer->lastName,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'timezone' => $customer->timezone,
            'preferences' => $customer->preferences,
            'source' => $customer->source,
            'notes' => $customer->notes,
            'lastSeenAt' => $customer->lastSeenAt,
        ];
    }
}

