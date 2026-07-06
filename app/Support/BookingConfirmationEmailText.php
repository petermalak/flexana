<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Models\Customer;

final class BookingConfirmationEmailText
{
    public static function customerSubject(): string
    {
        return 'Your Flexana booking confirmation';
    }

    public static function internalSubject(AppointmentModel $appointment): string
    {
        $appointment->loadMissing('branch');
        $branchName = trim((string) ($appointment->branch?->name ?? ''));

        if ($branchName === '') {
            return self::customerSubject();
        }

        return "[{$branchName}] " . self::customerSubject();
    }

    public static function body(
        BookingModel $booking,
        AppointmentModel $appointment,
        Customer $customer,
        ?PromoCodeModel $promo = null,
        ?float $subtotalBeforePromo = null,
        ?string $channel = null,
    ): string {
        $appointment->loadMissing(['service', 'provider', 'branch']);

        $service = $appointment->service;
        $provider = $appointment->provider;
        $branch = $appointment->branch;

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $appointmentDate = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'Y-m-d');
        $appointmentTime = ApiDateTime::formatInBusinessTimezone($appointment->booking_start, 'H:i');

        $promoLines = $promo !== null
            ? PromoEmailText::appliedSection(
                $promo,
                $subtotalBeforePromo ?? (float) $booking->total_amount,
                (float) $booking->total_amount,
            )
            : '';

        $branchLines = self::branchSection($branch?->name, $branch?->address);
        $channelLine = $channel !== null && $channel !== ''
            ? "* Channel: {$channel}\n\n"
            : '';

        return "Thank you for booking with Flexana!\n\n"
            . "Booking Details\n\n"
            . "* Name: {$customerName},\n\n"
            . "* Email: {$customer->email}\n\n"
            . "* Phone: {$customer->phone}\n\n"
            . "* Spots: " . ((int) ($booking->party_size ?? 1)) . "\n\n"
            . $branchLines
            . "* Class: " . ($service?->name ?? 'Unknown') . "\n\n"
            . "* Day: {$appointmentDate}\n\n"
            . "* Time: {$appointmentTime}\n\n"
            . "* Instructor: " . ($provider?->name ?? 'Unknown') . "\n\n"
            . "* Type: " . ($service?->description ?? '') . "\n\n"
            . $channelLine
            . $promoLines
            . "If you need to cancel, please do so at least 24 hours in advance via your Flexana account or by contacting us directly.\n\n"
            . "You can contact us at +20 122 0221100 to reschedule your session or request a refund.\n\n"
            . "We look forward to seeing you on the mat!\n\n"
            . "Flexana Team";
    }

    private static function branchSection(?string $name, ?string $address): string
    {
        $name = trim((string) ($name ?? ''));
        if ($name === '') {
            return '';
        }

        $lines = "* Branch: {$name}\n\n";
        $address = trim((string) ($address ?? ''));
        if ($address !== '') {
            $lines .= "* Address: {$address}\n\n";
        }

        return $lines;
    }
}
