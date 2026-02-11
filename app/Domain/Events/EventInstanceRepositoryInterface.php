<?php

namespace App\Domain\Events;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EventInstanceRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?EventInstance;

    public function findByEventUuid(string $eventUuid): array;

    public function findByDateRange(CarbonImmutable $start, CarbonImmutable $end): array;

    public function findAvailableInstances(string $eventUuid, int $partySize): array;

    public function create(array $payload): EventInstance;

    public function update(EventInstance $instance, array $payload): EventInstance;

    public function delete(EventInstance $instance): void;
}

