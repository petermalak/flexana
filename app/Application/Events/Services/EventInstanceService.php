<?php

namespace App\Application\Events\Services;

use App\Application\Events\Data\EventInstanceData;
use App\Application\Events\Data\EventInstanceUpsertData;
use App\Domain\Events\EventInstance;
use App\Domain\Events\EventInstanceRepositoryInterface;
use App\Domain\Events\EventRepositoryInterface;
use App\Domain\Staff\StaffRepositoryInterface;
use App\Support\ApiDateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EventInstanceService
{
    public function __construct(
        private readonly EventInstanceRepositoryInterface $eventInstances,
        private readonly EventRepositoryInterface $events,
        private readonly StaffRepositoryInterface $staff,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->eventInstances->paginate($filters, $perPage)
            ->through(fn (EventInstance $instance) => EventInstanceData::from($this->mapToArray($instance)));
    }

    public function create(EventInstanceUpsertData $payload): EventInstanceData
    {
        // Validate event
        $event = $this->events->findByUuid($payload->eventUuid);
        abort_if(!$event, 404, 'Event not found.');

        // Validate instructor if provided
        $instructor = null;
        if ($payload->instructorUuid) {
            $instructor = $this->staff->findByUuid($payload->instructorUuid);
            abort_if(!$instructor, 404, 'Instructor not found.');
        }

        // Validate dates
        if ($payload->bookingOpenDate && $payload->bookingCloseDate) {
            abort_if(
                $payload->bookingOpenDate >= $payload->bookingCloseDate,
                400,
                'Booking open date must be before booking close date.'
            );
        }
        if ($payload->bookingCloseDate && $payload->startsAt) {
            abort_if(
                $payload->bookingCloseDate >= $payload->startsAt,
                400,
                'Booking close date must be before event start date.'
            );
        }
        abort_if(
            $payload->startsAt >= $payload->endsAt,
            400,
            'Event start date must be before end date.'
        );

        $instanceData = $payload->toArray();
        unset($instanceData['eventUuid'], $instanceData['instructorUuid']);

        $instanceData['event_id'] = $event->id;
        if ($instructor) {
            $instanceData['instructor_id'] = $instructor->id;
        }

        $instance = $this->eventInstances->create($instanceData);

        return EventInstanceData::from($this->mapToArray($instance));
    }

    public function update(string $uuid, EventInstanceUpsertData $payload): EventInstanceData
    {
        $instance = $this->eventInstances->findByUuid($uuid);
        abort_if(!$instance, 404, 'Event instance not found.');

        // Validate event if changed
        if ($payload->eventUuid && $payload->eventUuid !== $instance->eventUuid) {
            $event = $this->events->findByUuid($payload->eventUuid);
            abort_if(!$event, 404, 'Event not found.');
        }

        // Validate instructor if provided
        $instructor = null;
        if ($payload->instructorUuid) {
            $instructor = $this->staff->findByUuid($payload->instructorUuid);
            abort_if(!$instructor, 404, 'Instructor not found.');
        }

        // Validate dates
        if ($payload->bookingOpenDate && $payload->bookingCloseDate) {
            abort_if(
                $payload->bookingOpenDate >= $payload->bookingCloseDate,
                400,
                'Booking open date must be before booking close date.'
            );
        }
        if ($payload->bookingCloseDate && $payload->startsAt) {
            abort_if(
                $payload->bookingCloseDate >= $payload->startsAt,
                400,
                'Booking close date must be before event start date.'
            );
        }
        abort_if(
            $payload->startsAt >= $payload->endsAt,
            400,
            'Event start date must be before end date.'
        );

        $instanceData = $payload->toArray();
        unset($instanceData['eventUuid'], $instanceData['instructorUuid']);

        if (isset($event)) {
            $instanceData['event_id'] = $event->id;
        }
        if ($instructor) {
            $instanceData['instructor_id'] = $instructor->id;
        }

        $updated = $this->eventInstances->update($instance, $instanceData);

        return EventInstanceData::from($this->mapToArray($updated));
    }

    public function delete(string $uuid): void
    {
        $instance = $this->eventInstances->findByUuid($uuid);
        abort_if(!$instance, 404, 'Event instance not found.');

        $this->eventInstances->delete($instance);
    }

    public function show(string $uuid): EventInstanceData
    {
        $instance = $this->eventInstances->findByUuid($uuid);
        abort_if(!$instance, 404, 'Event instance not found.');

        return EventInstanceData::from($this->mapToArray($instance));
    }

    private function mapToArray(EventInstance $instance): array
    {
        return [
            'uuid' => $instance->uuid,
            'eventUuid' => $instance->eventUuid,
            'startsAt' => ApiDateTime::toUtcIso8601($instance->startsAt),
            'endsAt' => ApiDateTime::toUtcIso8601($instance->endsAt),
            'capacity' => $instance->capacity,
            'location' => $instance->location,
            'status' => $instance->status,
            'resources' => $instance->resources,
            'bookingOpenDate' => ApiDateTime::toUtcIso8601($instance->bookingOpenDate),
            'bookingCloseDate' => ApiDateTime::toUtcIso8601($instance->bookingCloseDate),
            'instructorUuid' => $instance->instructorUuid,
        ];
    }
}

