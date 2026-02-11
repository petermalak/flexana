<?php

namespace App\Application\Staff\Services;

use App\Application\Staff\Data\StaffData;
use App\Application\Staff\Data\StaffUpsertData;
use App\Domain\Staff\StaffRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class StaffService
{
    public function __construct(
        private readonly StaffRepositoryInterface $staff,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->staff->paginate($filters, $perPage)
            ->through(fn ($staff) => StaffData::from($this->mapToArray($staff)));
    }

    public function create(StaffUpsertData $payload): StaffData
    {
        $staff = $this->staff->create($payload->toArray());

        return StaffData::from($this->mapToArray($staff));
    }

    public function update(string $uuid, StaffUpsertData $payload): StaffData
    {
        $staff = $this->staff->findByUuid($uuid);
        abort_if(!$staff, 404, 'Staff not found.');

        $updated = $this->staff->update($staff, $payload->toArray());

        return StaffData::from($this->mapToArray($updated));
    }

    public function delete(string $uuid): void
    {
        $staff = $this->staff->findByUuid($uuid);
        abort_if(!$staff, 404, 'Staff not found.');

        $this->staff->delete($staff);
    }

    public function show(string $uuid): StaffData
    {
        $staff = $this->staff->findByUuid($uuid);
        abort_if(!$staff, 404, 'Staff not found.');

        return StaffData::from($this->mapToArray($staff));
    }

    private function mapToArray($staff): array
    {
        return [
            'uuid' => $staff->uuid,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->phone,
            'role' => $staff->role,
            'colorHex' => $staff->color_hex,
            'timezone' => $staff->timezone,
            'skills' => $staff->skills,
            'isActive' => $staff->is_active,
            'createdAt' => $staff->created_at,
            'updatedAt' => $staff->updated_at,
        ];
    }
}

