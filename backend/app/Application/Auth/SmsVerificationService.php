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

        if ($driver === 'smsmisr' && $this->smsMisrConfigured()) {
            return $this->sendViaSmsMisr($phone, $code);
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

    /**
     * Send SMS via Twilio. Matches: POST Accounts/{Sid}/Messages.json, Basic Auth, form To/From/Body.
     */
    private function sendViaTwilio(string $to, string $code): bool
    {
        $sid = config('sms.twilio.account_sid');
        $token = config('sms.twilio.auth_token');
        $from = config('sms.twilio.from');
        $body = "Your verification code is: {$code}";

        $response = Http::timeout(15)
            ->withBasicAuth($sid, $token)
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
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        return true;
    }

    private function smsMisrConfigured(): bool
    {
        return ! empty(config('sms.smsmisr.username'))
            && ! empty(config('sms.smsmisr.password'))
            && ! empty(config('sms.smsmisr.sender_id'))
            && ! empty(config('sms.smsmisr.api_url'));
    }

    /**
     * Send SMS via SMS Misr (Egypt). API docs: https://smsmisr.com/API
     * POST request with all params in the URL query string (no body),
     * e.g. https://smsmisr.com/api/SMS/?environment=2&username=...&password=...&language=1&sender=...&mobile=2012...&message=Your+verification+code+is...
     * Success response: {"code": "1901", "SMSID": "...", "cost": "..."}.
     */
    private function sendViaSmsMisr(string $to, string $code): bool
    {
        $baseUrl = rtrim(config('sms.smsmisr.api_url'), '/');

        $mobile = str_replace('+', '', $to);
        $message = "Your verification code is: {$code}";

        $queryParams = [
            'environment' => config('sms.smsmisr.environment'),
            'username' => config('sms.smsmisr.username'),
            'password' => config('sms.smsmisr.password'),
            'language' => config('sms.smsmisr.language'),
            'sender' => config('sms.smsmisr.sender_id'),
            'mobile' => $mobile,
            'message' => $message,
        ];

        $url = $baseUrl . '?' . http_build_query($queryParams);

        // POST with no body, all params in query string (matches working curl example)
        $response = Http::timeout(15)->post($url);

        if (! $response->successful()) {
            Log::warning('SMS Misr send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        // SMS Misr may return plain text status code (e.g. 1901) or JSON.
        $body = trim($response->body());
        $successCodes = [0, '0', 1901, '1901'];

        // Try JSON first
        try {
            $data = $response->json();
        } catch (\Throwable) {
            $data = null;
        }

        if (is_array($data)) {
            $responseCode = $data['Code'] ?? $data['code'] ?? null;
            if ($responseCode !== null && ! in_array($responseCode, $successCodes, true)) {
                Log::warning('SMS Misr API error', [
                    'to' => $to,
                    'response' => $data,
                ]);

                return false;
            }

            return true;
        }

        // Fallback: plain numeric body
        if ($body !== '' && is_numeric($body) && ! in_array($body, $successCodes, true)) {
            Log::warning('SMS Misr API error (numeric body)', [
                'to' => $to,
                'body' => $body,
            ]);

            return false;
        }

        return true; // treat other 2xx responses as success
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
