<?php

namespace App\Domain\Staff;

interface StaffRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findByUuid(string $uuid): ?\App\Infrastructure\Persistence\Eloquent\StaffModel;

    public function create(array $payload): \App\Infrastructure\Persistence\Eloquent\StaffModel;

    public function update(\App\Infrastructure\Persistence\Eloquent\StaffModel $staff, array $payload): \App\Infrastructure\Persistence\Eloquent\StaffModel;

    public function delete(\App\Infrastructure\Persistence\Eloquent\StaffModel $staff): void;
}

