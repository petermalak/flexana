<?php

namespace App\Interfaces\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlutterBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $booking = $this->resource;
        
        // Access database attributes directly from Eloquent model
        // Eloquent automatically handles snake_case to camelCase conversion
        $id = $booking->id ?? null;
        $uuid = $booking->uuid ?? null;
        $status = $booking->getAttribute('status') ?? 'pending';
        $paymentStatus = $booking->getAttribute('payment_status') ?? 'pending';
        $customerId = $booking->getAttribute('customer_id') ?? null;
        $eventId = $booking->getAttribute('event_id') ?? null;
        $partySize = $booking->getAttribute('party_size') ?? 1;
        $totalAmount = $booking->getAttribute('total_amount') ?? 0;
        $depositAmount = $booking->getAttribute('deposit_amount') ?? 0;
        $balanceAmount = $booking->getAttribute('balance_amount') ?? 0;
        $currency = $booking->getAttribute('currency') ?? 'USD';
        $bookedAt = $booking->getAttribute('booked_at') ?? now();
        $createdAt = $booking->getAttribute('created_at') ?? now();
        $answers = $booking->getAttribute('answers') ?? null;
        $notes = $booking->getAttribute('notes') ?? null;
        
        // Format dates properly
        if ($bookedAt instanceof \Carbon\Carbon || $bookedAt instanceof \Carbon\CarbonImmutable) {
            $bookedAt = $bookedAt->toIso8601String();
        } elseif (is_string($bookedAt)) {
            $bookedAt = $bookedAt;
        } else {
            $bookedAt = now()->toIso8601String();
        }
        
        if ($createdAt instanceof \Carbon\Carbon || $createdAt instanceof \Carbon\CarbonImmutable) {
            $createdAt = $createdAt->toIso8601String();
        } elseif (is_string($createdAt)) {
            $createdAt = $createdAt;
        } else {
            $createdAt = now()->toIso8601String();
        }
        
        // Match WordPress/Emilia plugin response format
        return [
            'id' => $id,
            'uuid' => $uuid,
            'bookingNumber' => $uuid, // Common in booking systems
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'customerId' => $customerId,
            'serviceId' => $eventId,
            'eventId' => $eventId,
            'partySize' => (int) $partySize,
            'totalAmount' => (float) $totalAmount,
            'depositAmount' => (float) $depositAmount,
            'balanceAmount' => (float) $balanceAmount,
            'currency' => $currency,
            'bookedAt' => $bookedAt,
            'createdAt' => $createdAt,
            'answers' => $answers,
            'notes' => $notes,
        ];
    }
}

