<?php

namespace App\Interfaces\Http\Resources;

use App\Application\ClassTypes\Data\ClassTypeData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClassTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ClassTypeData $this */
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'colorHex' => $this->colorHex,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}

