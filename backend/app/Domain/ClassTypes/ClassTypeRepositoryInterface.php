<?php

namespace App\Domain\ClassTypes;

interface ClassTypeRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator;

    public function findByUuid(string $uuid): ?\App\Infrastructure\Persistence\Eloquent\ClassTypeModel;

    public function create(array $payload): \App\Infrastructure\Persistence\Eloquent\ClassTypeModel;

    public function update(\App\Infrastructure\Persistence\Eloquent\ClassTypeModel $classType, array $payload): \App\Infrastructure\Persistence\Eloquent\ClassTypeModel;

    public function delete(\App\Infrastructure\Persistence\Eloquent\ClassTypeModel $classType): void;
}

