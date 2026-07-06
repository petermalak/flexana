<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Models\Customer;

final class ClassReminderNotificationText
{
    public static function pushTitle(): string
    {
        return 'Class reminder';
    }

    public static function pushBody(BookingModel $booking, AppointmentModel $appointment, Customer $customer): string
    {
        $service = $appointment->service;
        $className = $service?->name ?? 'your class';
        $time = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i');
        $date = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'M j');

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $greeting = $customerName !== '' ? "Hi {$customerName}, " : '';

        return "{$greeting}your {$className} class is tomorrow ({$date}) at {$time}. See you on the mat!";
    }

    /**
     * @return array<string, string>
     */
    public static function pushData(BookingModel $booking, AppointmentModel $appointment): array
    {
        return [
            'type' => 'class_reminder',
            'bookingId' => (string) $booking->id,
            'sessionId' => (string) ($appointment->id ?? ''),
            'serviceName' => (string) ($appointment->service?->name ?? ''),
        ];
    }
}
