<?php

namespace App\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlutterServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $event = $this->resource;
        
        // Access database attributes directly from Eloquent model
        $meta = $event->getAttribute('meta') ?? [];
        $status = $event->getAttribute('status') ?? 'draft';
        $allowWaitlist = (bool) ($event->getAttribute('allow_waitlist') ?? false);
        $depositAmount = $event->getAttribute('deposit_amount') ?? 0;
        $capacity = $event->getAttribute('capacity') ?? null;
        
        // Match WordPress/Emilia plugin response format
        return [
            'id' => $event->id ?? null,
            'uuid' => $event->getAttribute('uuid') ?? null,
            'name' => $event->getAttribute('name') ?? null,
            'categoryId' => $event->getAttribute('category') ?? null,
            'description' => $event->getAttribute('description') ?? null,
            'status' => $this->mapStatusToFlutter($status),
            'price' => (float) ($event->getAttribute('price') ?? 0),
            'deposit' => (float) $depositAmount,
            'duration' => $meta['duration'] ?? null,
            'minCapacity' => $meta['minCapacity'] ?? 1,
            'maxCapacity' => $capacity ?? $meta['maxCapacity'] ?? 1,
            'providers' => $meta['providers'] ?? [],
            'extras' => $meta['extras'] ?? [],
            'color' => $meta['color'] ?? null,
            'pictureFullPath' => $meta['pictureFullPath'] ?? null,
            'pictureThumbPath' => $meta['pictureThumbPath'] ?? null,
            'settings' => $meta['settings'] ?? null,
            'gallery' => $meta['gallery'] ?? [],
            'position' => $meta['position'] ?? 0,
            'customPricing' => $meta['customPricing'] ?? null,
            'limitPerCustomer' => $meta['limitPerCustomer'] ?? null,
            'bringingAnyone' => $allowWaitlist,
            'show' => $status === 'published',
            'aggregatedPrice' => $meta['aggregatedPrice'] ?? false,
            'recurringCycle' => $meta['recurringCycle'] ?? 'disabled',
            'recurringSub' => $meta['recurringSub'] ?? 'future',
            'recurringPayment' => $meta['recurringPayment'] ?? 0,
            'depositPayment' => $meta['depositPayment'] ?? 'disabled',
            'depositPerPerson' => $meta['depositPerPerson'] ?? 1,
            'fullPayment' => $meta['fullPayment'] ?? false,
            'minSelectedExtras' => $meta['minSelectedExtras'] ?? null,
            'mandatoryExtra' => $meta['mandatoryExtra'] ?? false,
            'maxExtraPeople' => $meta['maxExtraPeople'] ?? null,
        ];
    }
    
    private function mapStatusToFlutter(string $status): string
    {
        $map = [
            'published' => 'visible',
            'draft' => 'hidden',
            'scheduled' => 'visible',
            'archived' => 'hidden',
        ];
        
        return $map[$status] ?? 'visible';
    }
}

