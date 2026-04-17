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

        // When we have a code to send (e.g. phone change), send it via SMS so the user receives that exact code.
        // Twilio Verify sends its own OTP and ignores our code, which would break flows that store our code in the DB.
        if ($driver === 'twilio' && $code !== '' && $this->twilioConfigured()) {
            return $this->sendViaTwilio($phone, $code);
        }

        if ($driver === 'twilio' && $this->twilioVerifyConfigured()) {
            return $this->sendViaTwilioVerify($phone);
        }

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

    public function checkVerification(string $phone, string $code): ?bool
    {
        $phone = $this->normalizeE164($phone);
        if ($phone === '' || $code === '') {
            return false;
        }

        if (config('sms.driver') === 'twilio' && $this->twilioVerifyConfigured()) {
            return $this->checkTwilioVerify($phone, $code);
        }

        return null;
    }

    private function twilioVerifyConfigured(): bool
    {
        return ! empty(config('sms.twilio.account_sid'))
            && ! empty(config('sms.twilio.auth_token'))
            && ! empty(config('sms.twilio.verify_service_sid'));
    }

    private function twilioConfigured(): bool
    {
        $channel = (string) config('sms.twilio.channel', 'sms');

        if ($channel === 'whatsapp') {
            return ! empty(config('sms.twilio.account_sid'))
                && ! empty(config('sms.twilio.auth_token'))
                && ! empty(config('sms.twilio.whatsapp_from'))
                && ! empty(config('sms.twilio.content_sid'));
        }

        return ! empty(config('sms.twilio.account_sid'))
            && ! empty(config('sms.twilio.auth_token'))
            && ! empty(config('sms.twilio.from'));
    }

    /**
     * Twilio Verify API: send verification (SMS). POST .../Verifications with To, Channel=sms
     */
    private function sendViaTwilioVerify(string $to): bool
    {
        $sid = config('sms.twilio.account_sid');
        $token = config('sms.twilio.auth_token');
        $serviceSid = config('sms.twilio.verify_service_sid');
        $url = "https://verify.twilio.com/v2/Services/{$serviceSid}/Verifications";

        $response = Http::timeout(15)
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->post($url, [
                'To' => $to,
                'Channel' => 'sms',
            ]);

        if (! $response->successful()) {
            Log::warning('Twilio Verify send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Twilio Verify API: check code. POST .../VerificationCheck with To, Code
     */
    private function checkTwilioVerify(string $to, string $code): bool
    {
        $sid = config('sms.twilio.account_sid');
        $token = config('sms.twilio.auth_token');
        $serviceSid = config('sms.twilio.verify_service_sid');
        $url = "https://verify.twilio.com/v2/Services/{$serviceSid}/VerificationCheck";

        $response = Http::timeout(15)
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->post($url, [
                'To' => $to,
                'Code' => $code,
            ]);

        if (! $response->successful()) {
            Log::info('Twilio Verify check failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        $data = $response->json();
        $status = $data['status'] ?? '';

        return strtolower($status) === 'approved';
    }

    /**
     * Send SMS via Twilio. Matches: POST Accounts/{Sid}/Messages.json, Basic Auth, form To/From/Body.
     */
    private function sendViaTwilio(string $to, string $code): bool
    {
        $sid = config('sms.twilio.account_sid');
        $token = config('sms.twilio.auth_token');
        $channel = (string) config('sms.twilio.channel', 'sms');

        // WhatsApp template message (Content API)
        if ($channel === 'whatsapp') {
            $from = (string) config('sms.twilio.whatsapp_from');
            $contentSid = (string) config('sms.twilio.content_sid');

            $payload = [
                'To' => $this->prefixWhatsApp($to),
                'From' => $this->prefixWhatsApp($from),
                'ContentSid' => $contentSid,
                // ContentVariables must be a JSON string
                'ContentVariables' => json_encode(['1' => (string) $code], JSON_UNESCAPED_SLASHES),
            ];

            $response = Http::timeout(15)
                ->withBasicAuth($sid, $token)
                ->asForm()
                ->post(
                    "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                    $payload
                );

            if (! $response->successful()) {
                Log::warning('Twilio WhatsApp OTP failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);

                return false;
            }

            return true;
        }

        // Default: SMS message
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

    private function prefixWhatsApp(string $value): string
    {
        $v = trim($value);
        if (str_starts_with($v, 'whatsapp:')) {
            return $v;
        }
        return 'whatsapp:' . $v;
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
     * POST request with application/x-www-form-urlencoded body.
     * Success response: {"code": "1901", "SMSID": "...", "cost": "..."}.
     */
    private function sendViaSmsMisr(string $to, string $code): bool
    {
        $baseUrl = rtrim(config('sms.smsmisr.api_url'), '/');

        $mobile = str_replace('+', '', $to);
        $message = "Your verification code is: {$code}";

        $environment = config('sms.smsmisr.environment');
        if (is_string($environment)) {
            $env = strtolower(trim($environment));
            // SMS Misr commonly uses numeric environment codes (e.g. 1=test, 2=production).
            // Accept human-friendly values from .env to avoid subtle misconfiguration.
            // According to SMS Misr docs: 1 = Live, 2 = Test
            if ($env === 'production' || $env === 'live') {
                $environment = '1';
            } elseif ($env === 'test' || $env === 'testing' || $env === 'sandbox') {
                $environment = '2';
            }
        }

        $queryParams = [
            'environment' => $environment,
            'username' => config('sms.smsmisr.username'),
            'password' => config('sms.smsmisr.password'),
            'language' => config('sms.smsmisr.language'),
            'sender' => config('sms.smsmisr.sender_id'),
            'mobile' => $mobile,
            'message' => $message,
        ];

        try {
            // SMS Misr expects application/x-www-form-urlencoded POST body.
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->retry(2, 250)
                ->asForm()
                ->post($baseUrl . '/', $queryParams);
        } catch (\Throwable $e) {
            $sanitizedParams = $queryParams;
            if (array_key_exists('password', $sanitizedParams)) {
                $sanitizedParams['password'] = '***';
            }
            Log::warning('SMS Misr send exception', [
                'to' => $to,
                'message' => $e->getMessage(),
                'endpoint' => $baseUrl,
                'query' => $sanitizedParams,
            ]);

            return false;
        }

        if (! $response->successful()) {
            $sanitizedBody = $response->json() ?? $response->body();
            Log::warning('SMS Misr send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $sanitizedBody,
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

            // Log success so we can correlate with SMS Misr console reports (SMSID, Cost, etc.)
            // Do not log credentials; only provider response metadata.
            Log::info('SMS Misr sent', [
                'to' => $to,
                'response' => [
                    'code' => $responseCode,
                    'SMSID' => $data['SMSID'] ?? $data['SmsID'] ?? $data['smsid'] ?? null,
                    'Cost' => $data['Cost'] ?? $data['cost'] ?? null,
                ],
            ]);

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
