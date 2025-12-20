<?php

namespace App\Domain\Services;

interface ServiceRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findByUuid(string $uuid): ?\App\Infrastructure\Persistence\Eloquent\ServiceModel;

    public function create(array $payload): \App\Infrastructure\Persistence\Eloquent\ServiceModel;

    public function update(\App\Infrastructure\Persistence\Eloquent\ServiceModel $service, array $payload): \App\Infrastructure\Persistence\Eloquent\ServiceModel;

    public function delete(\App\Infrastructure\Persistence\Eloquent\ServiceModel $service): void;
}

