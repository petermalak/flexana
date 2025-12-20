<?php

namespace App\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Application\Customers\Data\CustomerData */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'preferences' => $this->preferences,
            'source' => $this->source,
            'notes' => $this->notes,
            'lastSeenAt' => $this->lastSeenAt,
        ];
    }
}

