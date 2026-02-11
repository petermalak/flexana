<?php

namespace App\Application\Services\Services;

use App\Application\Services\Data\ServiceData;
use App\Application\Services\Data\ServiceUpsertData;
use App\Domain\Services\ServiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ServiceService
{
    public function __construct(
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->services->paginate($filters, $perPage)
            ->through(fn ($service) => ServiceData::from($this->mapToArray($service)));
    }

    public function create(ServiceUpsertData $payload): ServiceData
    {
        $service = $this->services->create($payload->toArray());

        return ServiceData::from($this->mapToArray($service));
    }

    public function update(string $uuid, ServiceUpsertData $payload): ServiceData
    {
        $service = $this->services->findByUuid($uuid);
        abort_if(!$service, 404, 'Service not found.');

        $updated = $this->services->update($service, $payload->toArray());

        return ServiceData::from($this->mapToArray($updated));
    }

    public function delete(string $uuid): void
    {
        $service = $this->services->findByUuid($uuid);
        abort_if(!$service, 404, 'Service not found.');

        $this->services->delete($service);
    }

    public function show(string $uuid): ServiceData
    {
        $service = $this->services->findByUuid($uuid);
        abort_if(!$service, 404, 'Service not found.');

        return ServiceData::from($this->mapToArray($service));
    }

    private function mapToArray($service): array
    {
        return [
            'uuid' => $service->uuid,
            'name' => $service->name,
            'description' => $service->description,
            'duration' => $service->duration,
            'price' => $service->price,
            'minCapacity' => $service->min_capacity,
            'maxCapacity' => $service->max_capacity,
            'colorHex' => $service->color_hex,
            'status' => $service->status,
            'createdAt' => $service->created_at,
            'updatedAt' => $service->updated_at,
        ];
    }
}

