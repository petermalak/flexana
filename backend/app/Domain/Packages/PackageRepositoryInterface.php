<?php

namespace App\Domain\Packages;

interface PackageRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findByUuid(string $uuid): ?\App\Infrastructure\Persistence\Eloquent\PackageModel;

    public function create(array $payload): \App\Infrastructure\Persistence\Eloquent\PackageModel;

    public function update(\App\Infrastructure\Persistence\Eloquent\PackageModel $package, array $payload): \App\Infrastructure\Persistence\Eloquent\PackageModel;

    public function delete(\App\Infrastructure\Persistence\Eloquent\PackageModel $package): void;

    public function incrementUsedSessions(\App\Infrastructure\Persistence\Eloquent\PackageModel $package, int $count = 1): void;
}

