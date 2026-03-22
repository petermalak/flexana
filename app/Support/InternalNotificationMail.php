<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Sends a second copy of a transactional message to the operations inbox.
 * Using CC/BCC to the same address as MAIL_FROM often results in no delivery on SMTP
 * (sender and CC target the same mailbox). A separate To: message avoids that.
 */
final class InternalNotificationMail
{
    /**
     * Same plain-text body and subject to the customer, then a separate message to the internal inbox.
     * Used for booking confirmation/cancellation and package purchase.
     */
    public static function sendCustomerAndInternalCopy(
        string $body,
        string $subject,
        string $customerEmail,
        string $customerName = '',
    ): void {
        Mail::raw($body, function ($message) use ($customerEmail, $customerName, $subject): void {
            if ($customerName !== '') {
                $message->to($customerEmail, $customerName);
            } else {
                $message->to($customerEmail);
            }
            $message->subject($subject);
        });

        try {
            self::sendCopy($body, $subject);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function sendCopy(string $body, string $subject): void
    {
        $address = config('mail.internal_copy.address');
        if (! is_string($address) || trim($address) === '') {
            return;
        }

        Mail::raw($body, function ($message) use ($address, $subject): void {
            $message->to($address)
                ->subject($subject);
        });
    }
}
