<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SmsVerificationService implements SmsVerificationServiceInterface
{
    public function sendCode(string $phone, string $code): bool
    {
        $phone = $this->normalizeE164($phone);
        $driver = config('sms.driver', 'log');

        if ($driver === 'twilio' && $this->twilioConfigured()) {
            return $this->sendViaTwilio($phone, $code);
        }

        // Log only in non-production or when no SMS provider is configured
        if (app()->environment('local', 'testing') || $driver === 'log') {
            Log::channel('stack')->info('SMS verification code', [
                'phone' => $phone,
                'code' => $code,
            ]);
        }

        return true;
    }

    private function twilioConfigured(): bool
    {
        return ! empty(config('sms.twilio.account_sid'))
            && ! empty(config('sms.twilio.auth_token'))
            && ! empty(config('sms.twilio.from'));
    }

    private function sendViaTwilio(string $to, string $code): bool
    {
        $sid = config('sms.twilio.account_sid');
        $token = config('sms.twilio.auth_token');
        $from = config('sms.twilio.from');
        $body = "Your verification code is: {$code}";

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post(
                "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                [
                    'To' => $to,
                    'From' => $from,
                    'Body' => $body,
                ]
            );

        if (! $response->successful()) {
            Log::warning('Twilio SMS failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            return false;
        }

        return true;
    }

    private function normalizeE164(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '+20' . substr($phone, 1); // Egyptian local
        }
        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
