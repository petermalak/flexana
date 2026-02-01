<?php

namespace App\Application\Auth;

interface SmsVerificationServiceInterface
{
    /**
     * Send a verification code to the given phone number.
     * Returns true if sent successfully (or simulated).
     */
    public function sendCode(string $phone, string $code): bool;
}
