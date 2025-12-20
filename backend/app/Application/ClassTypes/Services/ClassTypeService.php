<?php

namespace App\Application\ClassTypes\Services;

use App\Application\ClassTypes\Data\ClassTypeData;
use App\Application\ClassTypes\Data\ClassTypeUpsertData;
use App\Domain\ClassTypes\ClassTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ClassTypeService
{
    public function __construct(
        private readonly ClassTypeRepositoryInterface $classTypes,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->classTypes->paginate($filters, $perPage)
            ->through(fn ($classType) => ClassTypeData::from($this->mapToArray($classType)));
    }

    public function create(ClassTypeUpsertData $payload): ClassTypeData
    {
        $classType = $this->classTypes->create($payload->toArray());

        return ClassTypeData::from($this->mapToArray($classType));
    }

    public function update(string $uuid, ClassTypeUpsertData $payload): ClassTypeData
    {
        $classType = $this->classTypes->findByUuid($uuid);
        abort_if(!$classType, 404, 'Class type not found.');

        $updated = $this->classTypes->update($classType, $payload->toArray());

        return ClassTypeData::from($this->mapToArray($updated));
    }

    public function delete(string $uuid): void
    {
        $classType = $this->classTypes->findByUuid($uuid);
        abort_if(!$classType, 404, 'Class type not found.');

        $this->classTypes->delete($classType);
    }

    public function show(string $uuid): ClassTypeData
    {
        $classType = $this->classTypes->findByUuid($uuid);
        abort_if(!$classType, 404, 'Class type not found.');

        return ClassTypeData::from($this->mapToArray($classType));
    }

    private function mapToArray($classType): array
    {
        return [
            'uuid' => $classType->uuid,
            'name' => $classType->name,
            'slug' => $classType->slug,
            'description' => $classType->description,
            'colorHex' => $classType->color_hex,
            'isActive' => $classType->is_active,
            'createdAt' => $classType->created_at,
            'updatedAt' => $classType->updated_at,
        ];
    }
}

