<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Models\Customer;

final class ClassReminderEmailText
{
    public static function subject(): string
    {
        return 'Reminder: your Flexana class is tomorrow';
    }

    public static function body(BookingModel $booking, AppointmentModel $appointment, Customer $customer): string
    {
        $appointment->loadMissing(['service', 'provider', 'branch']);

        $service = $appointment->service;
        $provider = $appointment->provider;
        $branch = $appointment->branch
            ?? BranchSettings::resolveBranchModel(
                $appointment->branch_id ? (int) $appointment->branch_id : null,
            );

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDate = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'l, F j, Y');
        $appointmentTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i');

        $branchLine = '';
        $branchName = trim((string) ($branch?->name ?? ''));
        if ($branchName !== '') {
            $branchLine = "* Branch: {$branchName}\n\n";
            $address = trim((string) ($branch?->address ?? ''));
            if ($address !== '') {
                $branchLine .= "* Address: {$address}\n\n";
            }
        }

        return "Hi {$customerName},\n\n"
            . "This is a friendly reminder that your Flexana class is tomorrow.\n\n"
            . "Class details\n\n"
            . $branchLine
            . "* Class: " . ($service?->name ?? 'Unknown') . "\n\n"
            . "* Date: {$appointmentDate}\n\n"
            . "* Time: {$appointmentTime}\n\n"
            . "* Instructor: " . ($provider?->name ?? 'Unknown') . "\n\n"
            . "* Spots: " . ((int) ($booking->party_size ?? 1)) . "\n\n"
            . "If you need to cancel, please do so at least 24 hours in advance via your Flexana account or by contacting us directly.\n\n"
            . "You can contact us at +20 122 0221100 to reschedule your session.\n\n"
            . "We look forward to seeing you on the mat!\n\n"
            . "Flexana Team";
    }
}
