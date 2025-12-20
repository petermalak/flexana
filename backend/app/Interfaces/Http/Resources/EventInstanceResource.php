<?php

namespace App\Interfaces\Http\Resources;

use App\Application\Events\Data\EventInstanceData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventInstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EventInstanceData $this */
        return [
            'uuid' => $this->uuid,
            'eventUuid' => $this->eventUuid,
            'startsAt' => $this->startsAt,
            'endsAt' => $this->endsAt,
            'capacity' => $this->capacity,
            'location' => $this->location,
            'status' => $this->status,
            'resources' => $this->resources,
            'bookingOpenDate' => $this->bookingOpenDate,
            'bookingCloseDate' => $this->bookingCloseDate,
            'instructorUuid' => $this->instructorUuid,
        ];
    }
}

