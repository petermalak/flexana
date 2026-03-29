<?php

namespace App\Interfaces\Http\Resources;

use App\Support\ApiDateTime;
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
        $spots = (int) ($booking->getAttribute('party_size') ?? 1);
        $totalAmount = $booking->getAttribute('total_amount') ?? 0;
        $depositAmount = $booking->getAttribute('deposit_amount') ?? 0;
        $balanceAmount = $booking->getAttribute('balance_amount') ?? 0;
        $currency = $booking->getAttribute('currency') ?? 'USD';
        $bookedAt = ApiDateTime::toUtcIso8601($booking->getAttribute('booked_at') ?? now());
        $createdAt = ApiDateTime::toUtcIso8601($booking->getAttribute('created_at') ?? now());
        $answers = $booking->getAttribute('answers') ?? null;
        $notes = $booking->getAttribute('notes') ?? null;

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
            'spots' => $spots,
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

