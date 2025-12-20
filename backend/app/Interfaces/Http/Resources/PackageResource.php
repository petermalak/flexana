<?php

namespace App\Interfaces\Http\Resources;

use App\Application\Packages\Data\PackageData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PackageData $this */
        return [
            'uuid' => $this->uuid,
            'classType' => $this->classType,
            'title' => $this->title,
            'description' => $this->description,
            'totalSessions' => $this->totalSessions,
            'usedSessions' => $this->usedSessions,
            'discount' => $this->discount,
            'price' => $this->price,
            'expiry' => $this->expiry,
            'status' => $this->status,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

