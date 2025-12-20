<?php

namespace App\Application\Packages\Services;

use App\Application\Packages\Data\PackageData;
use App\Application\Packages\Data\PackageUpsertData;
use App\Domain\ClassTypes\ClassTypeRepositoryInterface;
use App\Domain\Packages\PackageRepositoryInterface;
use App\Domain\Services\ServiceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PackageService
{
    public function __construct(
        private readonly PackageRepositoryInterface $packages,
        private readonly ClassTypeRepositoryInterface $classTypes,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->packages->paginate($filters, $perPage)
            ->through(fn ($package) => PackageData::from($this->mapToArray($package)));
    }

    public function create(PackageUpsertData $payload): PackageData
    {
        // Validate class type if provided
        if ($payload->classTypeUuid) {
            $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
            abort_if(!$classType, 404, 'Class type not found.');
        }

        $packageData = $payload->toArray();
        unset($packageData['classTypeUuid'], $packageData['serviceUuids']);
        
        if ($payload->classTypeUuid) {
            $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
            $packageData['class_type_id'] = $classType->id;
        }

        if (isset($payload->serviceUuids)) {
            $packageData['service_ids'] = $this->resolveServiceIds($payload->serviceUuids);
        }

        $package = $this->packages->create($packageData);

        return PackageData::from($this->mapToArray($package));
    }

    public function update(string $uuid, PackageUpsertData $payload): PackageData
    {
        $package = $this->packages->findByUuid($uuid);
        abort_if(!$package, 404, 'Package not found.');

        // Validate class type if provided
        if ($payload->classTypeUuid) {
            $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
            abort_if(!$classType, 404, 'Class type not found.');
        }

        $packageData = $payload->toArray();
        unset($packageData['classTypeUuid'], $packageData['serviceUuids']);
        
        if ($payload->classTypeUuid) {
            $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
            $packageData['class_type_id'] = $classType->id;
        }

        if (isset($payload->serviceUuids)) {
            $packageData['service_ids'] = $this->resolveServiceIds($payload->serviceUuids);
        }

        $updated = $this->packages->update($package, $packageData);

        return PackageData::from($this->mapToArray($updated));
    }

    public function delete(string $uuid): void
    {
        $package = $this->packages->findByUuid($uuid);
        abort_if(!$package, 404, 'Package not found.');

        $this->packages->delete($package);
    }

    public function show(string $uuid): PackageData
    {
        $package = $this->packages->findByUuid($uuid);
        abort_if(!$package, 404, 'Package not found.');

        return PackageData::from($this->mapToArray($package));
    }

    private function resolveServiceIds(array $serviceUuids): array
    {
        $serviceIds = [];
        foreach ($serviceUuids as $serviceUuid) {
            $service = $this->services->findByUuid($serviceUuid);
            abort_if(!$service, 404, "Service not found: {$serviceUuid}");
            $serviceIds[] = $service->id;
        }
        return $serviceIds;
    }

    private function mapToArray($package): array
    {
        return [
            'uuid' => $package->uuid,
            'classType' => $package->classType ? [
                'uuid' => $package->classType->uuid,
                'name' => $package->classType->name,
            ] : null,
            'title' => $package->title,
            'description' => $package->description,
            'totalSessions' => $package->total_sessions,
            'usedSessions' => $package->used_sessions ?? 0,
            'discount' => $package->discount,
            'price' => $package->price,
            'expiry' => $package->expiry?->toDateString(),
            'status' => $package->status,
            'createdAt' => $package->created_at,
            'updatedAt' => $package->updated_at,
        ];
    }
}

