<?php

namespace App\Interfaces\Http\Resources;

use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlutterCustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = $this->resource;

        // Access database attributes directly from Eloquent model
        $preferences = $customer->getAttribute('preferences') ?? [];
        $firstName = $customer->getAttribute('first_name') ?? null;
        $lastName = $customer->getAttribute('last_name') ?? null;
        $lastSeenAt = ApiDateTime::toUtcIso8601($customer->getAttribute('last_seen_at'));

        // Match WordPress/Emilia plugin response format
        return [
            'id' => $customer->id ?? null,
            'uuid' => $customer->getAttribute('uuid') ?? null,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $customer->getAttribute('email') ?? null,
            'phone' => $customer->getAttribute('phone') ?? null,
            'countryPhoneIso' => $preferences['countryPhoneIso'] ?? null,
            'externalId' => $preferences['externalId'] ?? null,
            'gender' => $preferences['gender'] ?? null,
            'birthday' => $preferences['birthday'] ?? null,
            'language' => $preferences['language'] ?? null,
            'note' => $customer->getAttribute('notes') ?? null,
            'timezone' => $customer->getAttribute('timezone') ?? 'UTC',
            'source' => $customer->getAttribute('source') ?? null,
            'lastSeenAt' => $lastSeenAt,
        ];
    }
}

