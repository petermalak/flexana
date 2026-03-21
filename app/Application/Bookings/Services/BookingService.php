<?php

namespace App\Application\Bookings\Services;

use App\Application\Bookings\Data\BookingCreateData;
use App\Application\Bookings\Data\BookingData;
use App\Domain\Bookings\Booking;
use App\Domain\Bookings\BookingRepositoryInterface;
use App\Domain\Customers\CustomerRepositoryInterface;
use App\Domain\Events\EventInstanceRepositoryInterface;
use App\Domain\Events\EventRepositoryInterface;
use App\Domain\Packages\PackageRepositoryInterface;
use App\Domain\Services\ServiceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EventModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class BookingService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookings,
        private readonly EventRepositoryInterface $events,
        private readonly EventInstanceRepositoryInterface $eventInstances,
        private readonly CustomerRepositoryInterface $customers,
        private readonly PackageRepositoryInterface $packages,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->bookings->paginate($filters, $perPage)
            ->through(fn (Booking $booking) => BookingData::from($this->mapToArray($booking)));
    }

    public function create(BookingCreateData $payload): BookingData
    {
        return DB::transaction(function () use ($payload) {
            // Find event
            $event = $this->events->findByUuid($payload->eventUuid);
            abort_if(!$event, 404, 'Event not found.');

            // Find customer
            $customer = $this->customers->findByUuid($payload->customerUuid);
            abort_if(!$customer, 404, 'Customer not found.');

            // Find or validate event instance
            $eventInstance = null;
            $eventInstanceModel = null;
            if ($payload->eventInstanceUuid) {
                $eventInstance = $this->eventInstances->findByUuid($payload->eventInstanceUuid);
                abort_if(!$eventInstance, 404, 'Event instance not found.');
                abort_if($eventInstance->eventUuid !== $event->uuid, 400, 'Event instance does not belong to event.');

                $eventInstanceModel = EventModel::find($event->id)?->instances()->where('uuid', $payload->eventInstanceUuid)->first();
            }

            // Validate booking dates if instance exists
            if ($eventInstance) {
                $now = now();
                if ($eventInstance->bookingOpenDate && $now < $eventInstance->bookingOpenDate) {
                    abort(400, 'Booking is not yet open.');
                }
                if ($eventInstance->bookingCloseDate && $now > $eventInstance->bookingCloseDate) {
                    abort(400, 'Booking is closed.');
                }
                if ($now > $eventInstance->startsAt) {
                    abort(400, 'Cannot book past events.');
                }

                // Validate capacity
                $existingBookings = $this->bookings->countByEventInstance($eventInstance->id);
                $availableCapacity = $eventInstance->capacity ?? $event->capacity;
                if ($availableCapacity && ($existingBookings + $payload->partySize > $availableCapacity)) {
                    abort(400, 'Capacity exceeded. Available: ' . max(0, $availableCapacity - $existingBookings));
                }
            }

            // Find package if provided
            $package = null;
            $packageModel = null;
            if ($payload->packageUuid) {
                $packageModel = $this->packages->findByUuid($payload->packageUuid);
                abort_if(!$packageModel, 404, 'Package not found.');

                // Validate package
                if ($packageModel->expiry && now() > $packageModel->expiry) {
                    abort(400, 'Package has expired.');
                }
                if ($packageModel->used_sessions >= $packageModel->total_sessions) {
                    abort(400, 'Package has no remaining sessions.');
                }
                if ($packageModel->status !== 'active') {
                    abort(400, 'Package is not active.');
                }
                // Validate package class type matches event class type
                $eventModel = EventModel::find($event->id);
                if ($packageModel->class_type_id && $eventModel && $eventModel->class_type_id !== $packageModel->class_type_id) {
                    abort(400, 'Package class type does not match event class type.');
                }
            }

            // Find service if provided
            $serviceModel = null;
            if ($payload->serviceUuid) {
                $serviceModel = $this->services->findByUuid($payload->serviceUuid);
                abort_if(!$serviceModel, 404, 'Service not found.');
                if ($serviceModel->status !== 'visible') {
                    abort(400, 'Service is not available.');
                }
            }

            // Calculate pricing
            $totalAmount = $this->calculateBookingPrice($event, $eventInstanceModel, $serviceModel, $packageModel, $payload->partySize);

            // Apply package discount if applicable
            if ($packageModel) {
                $discount = (float) $packageModel->discount;
                $totalAmount = max(0, $totalAmount - $discount);
            }

            // Validate calculated amount matches provided amount (allow small rounding differences)
            if (abs($totalAmount - $payload->totalAmount) > 0.01) {
                abort(400, 'Calculated amount does not match provided amount. Calculated: ' . $totalAmount . ', Provided: ' . $payload->totalAmount);
            }

            // Create booking
            $booking = $this->bookings->create([
                'event_id' => $event->id,
                'event_instance_id' => $eventInstance?->id,
                'customer_id' => $customer->id,
                'package_id' => $packageModel?->id,
                'service_id' => $serviceModel?->id,
                'status' => 'pending',
                'payment_status' => 'pending',
                'party_size' => $payload->partySize,
                'total_amount' => $totalAmount,
                'deposit_amount' => $payload->depositAmount,
                'balance_amount' => $payload->balanceAmount,
                'currency' => $payload->currency,
                'channel' => $payload->channel,
                'answers' => $payload->answers,
                'notes' => $payload->notes,
            ]);

            // Increment package usage if applicable
            if ($packageModel) {
                $this->packages->incrementUsedSessions($packageModel, 1);
            }

            return BookingData::from($this->mapToArray($booking));
        });
    }

    private function calculateBookingPrice(
        $event,
        $eventInstanceModel,
        $serviceModel,
        $packageModel,
        int $partySize
    ): float {
        // If service is provided, use service price from event-service pivot or service default
        if ($serviceModel) {
            $eventModel = EventModel::find($event->id);
            if ($eventModel) {
                $pivot = $eventModel->services()->where('services.id', $serviceModel->id)->first()?->pivot;
                $servicePrice = $pivot?->price ?? $serviceModel->price;
                return (float) $servicePrice * $partySize;
            }
            return (float) $serviceModel->price * $partySize;
        }

        // Otherwise use event price
        return (float) $event->price * $partySize;
    }

    private function mapToArray(Booking $booking): array
    {
        return [
            'uuid' => $booking->uuid,
            'eventUuid' => $booking->eventUuid,
            'eventInstanceUuid' => $booking->eventInstanceUuid,
            'customerUuid' => $booking->customerUuid,
            'status' => $booking->status->value,
            'paymentStatus' => $booking->paymentStatus,
            'partySize' => $booking->partySize,
            'totalAmount' => $booking->totalAmount,
            'depositAmount' => $booking->depositAmount,
            'balanceAmount' => $booking->balanceAmount,
            'currency' => $booking->currency,
            'channel' => $booking->channel,
            'answers' => $booking->answers,
            'notes' => $booking->notes,
            'bookedAt' => $booking->bookedAt,
            'cancelledAt' => $booking->cancelledAt,
        ];
    }
}
