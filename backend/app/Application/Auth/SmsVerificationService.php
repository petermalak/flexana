<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Log;

final class SmsVerificationService implements SmsVerificationServiceInterface
{
    public function sendCode(string $phone, string $code): bool
    {
        // TODO: Integrate Twilio, AWS SNS, or another SMS provider.
        // For development/testing, log the code. Never log in production.
        if (app()->environment('local', 'testing')) {
            Log::channel('stack')->info('SMS verification code', [
                'phone' => $phone,
                'code' => $code,
            ]);
        }

        return true;
    }
}
