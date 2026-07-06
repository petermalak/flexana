<?php

namespace App\Application\Auth;

use App\Support\PhoneNumberNormalizer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SmsVerificationService implements SmsVerificationServiceInterface
{
    public function sendCode(string $phone, string $code): bool
    {
        $phone = PhoneNumberNormalizer::normalize($phone);
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
            // Prefer OTP API (template-based) when configured and we have a code to send.
            if ($code !== '' && $this->smsMisrOtpConfigured()) {
                return $this->sendViaSmsMisrOtp($phone, $code);
            }

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
        $phone = PhoneNumberNormalizer::normalize($phone);
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

    private function smsMisrOtpConfigured(): bool
    {
        return $this->smsMisrConfigured()
            && ! empty(config('sms.smsmisr.otp_api_url'))
            && ! empty(config('sms.smsmisr.otp_template'));
    }

    /**
     * Send OTP via SMS Misr OTP API (template-based). API docs: https://smsmisr.com/API
     * POST request with application/x-www-form-urlencoded body.
     * Success response: {"code": "4901", "SMSID": "...", "Cost": "..."}.
     */
    private function sendViaSmsMisrOtp(string $to, string $code): bool
    {
        $baseUrl = rtrim((string) config('sms.smsmisr.otp_api_url'), '/');

        $mobile = str_replace('+', '', $to);
        $environment = config('sms.smsmisr.environment');
        if (is_string($environment)) {
            $env = strtolower(trim($environment));
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
            'sender' => config('sms.smsmisr.sender_id'),
            'mobile' => $mobile,
            'template' => config('sms.smsmisr.otp_template'),
            'otp' => (string) $code,
        ];

        $startedAt = microtime(true);

        try {
            $response = Http::connectTimeout(10)
                ->timeout(30)
                ->asForm()
                ->post($baseUrl . '/', $queryParams);
        } catch (\Throwable $e) {
            $sanitizedParams = $queryParams;
            if (array_key_exists('password', $sanitizedParams)) {
                $sanitizedParams['password'] = '***';
            }
            Log::warning('SMS Misr OTP send exception', [
                'to' => $to,
                'message' => $e->getMessage(),
                'endpoint' => $baseUrl,
                'query' => $sanitizedParams,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        if (! $response->successful()) {
            $sanitizedBody = $response->json() ?? $response->body();
            Log::warning('SMS Misr OTP send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $sanitizedBody,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        $successCodes = [4901, '4901'];
        $data = null;
        try {
            $data = $response->json();
        } catch (\Throwable) {
            $data = null;
        }

        if (is_array($data)) {
            $responseCode = $data['Code'] ?? $data['code'] ?? null;
            if ($responseCode !== null && ! in_array($responseCode, $successCodes, true)) {
                Log::warning('SMS Misr OTP API error', [
                    'to' => $to,
                    'response' => $data,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return false;
            }

            Log::info('SMS Misr OTP sent', [
                'to' => $to,
                'response' => [
                    'code' => $responseCode,
                    'SMSID' => $data['SMSID'] ?? $data['SmsID'] ?? $data['smsid'] ?? null,
                    'Cost' => $data['Cost'] ?? $data['cost'] ?? null,
                ],
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        }

        // Some providers may return numeric code as plain body; handle that too.
        $body = trim($response->body());
        if ($body !== '' && is_numeric($body) && ! in_array($body, $successCodes, true)) {
            Log::warning('SMS Misr OTP API error (numeric body)', [
                'to' => $to,
                'body' => $body,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        // For anything else (e.g. unexpected plain-text), treat as failure to avoid false positives
        // that could hide provider-side rejections while still consuming balance.
        if ($body !== '' && ! is_numeric($body)) {
            Log::warning('SMS Misr OTP API unexpected response body', [
                'to' => $to,
                'body' => $body,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Send SMS via SMS Misr (Egypt). API docs: https://smsmisr.com/API
     * POST request with application/x-www-form-urlencoded body.
     * Success response: {"code": "1901", "SMSID": "...", "cost": "..."}.
     */
    private function sendViaSmsMisr(string $to, string $code): bool
    {
        $baseUrl = rtrim(config('sms.smsmisr.api_url'), '/');

        // Guardrail: OTP endpoint requires template+otp (different parameters).
        // If someone mistakenly points SMSMISR_API_URL to /api/OTP/, fail fast with an actionable log.
        if (str_contains(strtolower($baseUrl), '/api/otp')) {
            Log::warning('SMS Misr misconfiguration: SMSMISR_API_URL points to OTP endpoint', [
                'to' => $to,
                'api_url' => $baseUrl,
                'hint' => 'Set SMSMISR_API_URL=https://smsmisr.com/api/SMS/ and use SMSMISR_OTP_API_URL + SMSMISR_OTP_TEMPLATE for OTP API. If .env is already correct, clear stale config cache: php artisan config:clear (or optimize:clear), then restart php-fpm/queue workers.',
            ]);

            return false;
        }

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

        $startedAt = microtime(true);

        try {
            // SMS Misr expects application/x-www-form-urlencoded POST body.
            $response = Http::connectTimeout(10)
                ->timeout(30)
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
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        if (! $response->successful()) {
            $sanitizedBody = $response->json() ?? $response->body();
            Log::warning('SMS Misr send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $sanitizedBody,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
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
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return true;
        }

        // Fallback: plain numeric body
        if ($body !== '' && is_numeric($body) && ! in_array($body, $successCodes, true)) {
            Log::warning('SMS Misr API error (numeric body)', [
                'to' => $to,
                'body' => $body,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        // If we get a non-numeric plain-text body, treat as failure to avoid false-positive "sent".
        if ($body !== '' && ! is_numeric($body)) {
            Log::warning('SMS Misr API unexpected response body', [
                'to' => $to,
                'body' => $body,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return false;
        }

        return true;
    }

}
