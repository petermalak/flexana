<?php

namespace App\Domain\Bookings;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookingRepositoryInterface
{
    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator;

    public function create(array $payload): Booking;

    public function update(Booking $booking, array $payload): Booking;

    public function findByUuid(string $uuid): ?Booking;

    public function countByEventInstance(int $eventInstanceId): int;

    public function findByEventInstance(int $eventInstanceId): array;

    public function findByPackage(string $packageUuid): array;

    public function findByCustomer(string $customerUuid): array;
}

