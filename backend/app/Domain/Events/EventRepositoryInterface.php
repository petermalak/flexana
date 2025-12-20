<?php

namespace App\Domain\Events;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Event;

    public function create(array $payload): Event;

    public function update(Event $event, array $payload): Event;

    public function delete(Event $event): void;
}

