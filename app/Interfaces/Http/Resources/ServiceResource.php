<?php

namespace App\Interfaces\Http\Resources;

use App\Application\Services\Data\ServiceData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ServiceData $this */
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => $this->price,
            'minCapacity' => $this->minCapacity,
            'maxCapacity' => $this->maxCapacity,
            'colorHex' => $this->colorHex,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

