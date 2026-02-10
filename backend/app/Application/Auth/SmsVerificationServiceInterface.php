<?php

namespace App\Application\Auth;

interface SmsVerificationServiceInterface
{
    /**
     * Send a verification code to the given phone number.
     * Returns true if sent successfully (or simulated).
     */
    public function sendCode(string $phone, string $code): bool;

    /**
     * Check a verification code with the provider (e.g. Twilio Verify VerificationCheck).
     * Returns true if valid, false if invalid/expired, null if provider does not perform check (use DB).
     */
    public function checkVerification(string $phone, string $code): ?bool;
}
