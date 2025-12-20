<?php

namespace App\Application\Events\Services;

use App\Application\Events\Data\EventData;
use App\Application\Events\Data\EventUpsertData;
use App\Domain\ClassTypes\ClassTypeRepositoryInterface;
use App\Domain\Events\Event;
use App\Domain\Events\EventRepositoryInterface;
use App\Domain\Services\ServiceRepositoryInterface;
use App\Domain\Staff\StaffRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EventModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EventService
{
    public function __construct(
        private readonly EventRepositoryInterface $events,
        private readonly StaffRepositoryInterface $staff,
        private readonly ClassTypeRepositoryInterface $classTypes,
        private readonly ServiceRepositoryInterface $services,
    ) {
    }

    public function paginate(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->events->paginate($filters, $perPage)
            ->through(fn (Event $event) => EventData::from($this->mapToArray($event)));
    }

    public function create(EventUpsertData $payload): EventData
    {
        return DB::transaction(function () use ($payload) {
            // Validate instructor if provided
            $instructor = null;
            if ($payload->instructorUuid) {
                $instructor = $this->staff->findByUuid($payload->instructorUuid);
                abort_if(!$instructor, 404, 'Instructor not found.');
                abort_if(!$instructor->is_active, 400, 'Instructor is not active.');
            }

            // Validate class type if provided
            $classType = null;
            if ($payload->classTypeUuid) {
                $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
                abort_if(!$classType, 404, 'Class type not found.');
                abort_if(!$classType->is_active, 400, 'Class type is not active.');
            }

            // Prepare event data
            $eventData = $payload->toArray();
            unset($eventData['instructorUuid'], $eventData['classTypeUuid'], $eventData['serviceUuids']);
            
            $eventData['instructor_id'] = $instructor?->id;
            $eventData['class_type_id'] = $classType?->id;

            // Create event
            $event = $this->events->create($eventData);

            // Attach services if provided
            if ($payload->serviceUuids && count($payload->serviceUuids) > 0) {
                $this->attachServicesToEvent($event, $payload->serviceUuids);
            }

            return EventData::from($this->mapToArray($event));
        });
    }

    public function update(string $uuid, EventUpsertData $payload): EventData
    {
        return DB::transaction(function () use ($uuid, $payload) {
            $event = $this->events->findByUuid($uuid);
            abort_if(!$event, 404, 'Event not found.');

            // Validate instructor if provided
            $instructor = null;
            if ($payload->instructorUuid) {
                $instructor = $this->staff->findByUuid($payload->instructorUuid);
                abort_if(!$instructor, 404, 'Instructor not found.');
                abort_if(!$instructor->is_active, 400, 'Instructor is not active.');
            }

            // Validate class type if provided
            $classType = null;
            if ($payload->classTypeUuid) {
                $classType = $this->classTypes->findByUuid($payload->classTypeUuid);
                abort_if(!$classType, 404, 'Class type not found.');
                abort_if(!$classType->is_active, 400, 'Class type is not active.');
            }

            // Prepare event data
            $eventData = $payload->toArray();
            unset($eventData['instructorUuid'], $eventData['classTypeUuid'], $eventData['serviceUuids']);
            
            $eventData['instructor_id'] = $instructor?->id;
            $eventData['class_type_id'] = $classType?->id;

            // Update event
            $updated = $this->events->update($event, $eventData);

            // Update services if provided
            if ($payload->serviceUuids !== null) {
                if (count($payload->serviceUuids) > 0) {
                    $this->attachServicesToEvent($updated, $payload->serviceUuids);
                } else {
                    // Detach all services if empty array
                    $eventModel = EventModel::find($updated->id);
                    $eventModel?->services()->detach();
                }
            }

            return EventData::from($this->mapToArray($updated));
        });
    }

    public function delete(string $uuid): void
    {
        $event = $this->events->findByUuid($uuid);
        abort_if(!$event, 404, 'Event not found.');
        $this->events->delete($event);
    }

    private function attachServicesToEvent(Event $event, array $serviceUuids): void
    {
        $eventModel = EventModel::find($event->id);
        if (!$eventModel) {
            return;
        }

        $serviceIds = [];
        foreach ($serviceUuids as $serviceUuid) {
            $service = $this->services->findByUuid($serviceUuid);
            abort_if(!$service, 404, "Service not found: {$serviceUuid}");
            abort_if($service->status !== 'visible', 400, "Service is not available: {$serviceUuid}");
            $serviceIds[] = $service->id;
        }

        // Sync services (this will detach services not in the list and attach new ones)
        // Note: Prices should be set via separate endpoint or included in serviceUuids as associative array
        $eventModel->services()->sync($serviceIds);
    }

    private function mapToArray(Event $event): array
    {
        $eventModel = EventModel::find($event->id);
        
        return [
            'uuid' => $event->uuid,
            'slug' => $event->slug,
            'name' => $event->name,
            'category' => $event->category,
            'status' => $event->status->value,
            'timezone' => $event->timezone,
            'description' => $event->description,
            'capacity' => $event->capacity,
            'price' => $event->price,
            'depositAmount' => $event->depositAmount,
            'allowWaitlist' => $event->allowWaitlist,
            'recurrence' => $event->recurrence,
            'meta' => $event->meta,
            'publishedAt' => $event->publishedAt,
            'instructor' => $eventModel?->instructor ? [
                'uuid' => $eventModel->instructor->uuid,
                'name' => $eventModel->instructor->name,
                'email' => $eventModel->instructor->email,
            ] : null,
            'classType' => $eventModel?->classType ? [
                'uuid' => $eventModel->classType->uuid,
                'name' => $eventModel->classType->name,
                'slug' => $eventModel->classType->slug,
            ] : null,
            'services' => $eventModel?->services->map(fn($service) => [
                'uuid' => $service->uuid,
                'name' => $service->name,
                'price' => $service->pivot->price ?? $service->price,
                'description' => $service->description,
            ])->toArray() ?? [],
        ];
    }
}
