<?php

namespace App\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Application\Bookings\Data\BookingData */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'eventUuid' => $this->eventUuid,
            'eventInstanceUuid' => $this->eventInstanceUuid,
            'customerUuid' => $this->customerUuid,
            'status' => $this->status,
            'paymentStatus' => $this->paymentStatus,
            'partySize' => $this->partySize,
            'totalAmount' => $this->totalAmount,
            'depositAmount' => $this->depositAmount,
            'balanceAmount' => $this->balanceAmount,
            'currency' => $this->currency,
            'channel' => $this->channel,
            'answers' => $this->answers,
            'notes' => $this->notes,
            'bookedAt' => $this->bookedAt,
            'cancelledAt' => $this->cancelledAt,
        ];
    }
}

