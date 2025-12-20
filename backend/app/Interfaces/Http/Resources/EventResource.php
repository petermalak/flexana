<?php

namespace App\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Application\Events\Data\EventData */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status,
            'timezone' => $this->timezone,
            'description' => $this->description,
            'capacity' => $this->capacity,
            'price' => $this->price,
            'depositAmount' => $this->depositAmount,
            'allowWaitlist' => $this->allowWaitlist,
            'recurrence' => $this->recurrence,
            'meta' => $this->meta,
            'publishedAt' => $this->publishedAt,
        ];
    }
}

