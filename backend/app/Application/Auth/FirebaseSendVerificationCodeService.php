<?php

namespace App\Application\Auth;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FirebaseSendVerificationCodeService
{
    private const SEND_VERIFICATION_CODE_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:sendVerificationCode';

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    /**
     * Ask Firebase/Identity Platform to send an SMS verification code to the phone.
     * Returns sessionInfo for the client to use with signInWithPhoneNumber(code) to get the ID token.
     *
     * For mobile: send one of playIntegrityToken (Android), safetyNetToken (Android), or iosReceipt+iosSecret (iOS).
     * For web / testing: send recaptchaToken + optional recaptchaVersion.
     *
     * @param  array{recaptchaToken?: string, recaptchaVersion?: string, playIntegrityToken?: string, safetyNetToken?: string, iosReceipt?: string, iosSecret?: string, locale?: string}  $options
     * @return array{success: bool, message: string, sessionInfo?: string}
     */
    public function sendCode(string $phoneNumber, array $options = []): array
    {
        $phoneNumber = $this->normalizeToE164($phoneNumber);
        if (empty($phoneNumber)) {
            return ['success' => false, 'message' => 'Invalid phone number.'];
        }

        $body = array_filter([
            'phoneNumber' => $phoneNumber,
            'recaptchaToken' => $options['recaptchaToken'] ?? null,
            'recaptchaVersion' => $options['recaptchaVersion'] ?? null,
            'playIntegrityToken' => $options['playIntegrityToken'] ?? null,
            'safetyNetToken' => $options['safetyNetToken'] ?? null,
            'iosReceipt' => $options['iosReceipt'] ?? null,
            'iosSecret' => $options['iosSecret'] ?? null,
        ]);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Firebase-Locale' => $options['locale'] ?? 'en',
                ])
                ->post(self::SEND_VERIFICATION_CODE_URL . '?key=' . $this->apiKey, $body);

            $data = $response->json();

            if (! $response->successful()) {
                $message = $data['error']['message'] ?? $response->body();
                Log::warning('Firebase sendVerificationCode failed', [
                    'phone' => $phoneNumber,
                    'status' => $response->status(),
                    'message' => $message,
                ]);

                return [
                    'success' => false,
                    'message' => $this->humanMessage($data['error']['message'] ?? $message),
                ];
            }

            $sessionInfo = $data['sessionInfo'] ?? null;
            if (empty($sessionInfo)) {
                return ['success' => false, 'message' => 'Firebase did not return session info.'];
            }

            return [
                'success' => true,
                'message' => 'Verification code sent.',
                'sessionInfo' => $sessionInfo,
            ];
        } catch (RequestException $e) {
            Log::warning('Firebase sendVerificationCode request failed', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Could not send verification code. Please try again.',
            ];
        }
    }

    private function normalizeToE164(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if (str_starts_with($phone, '0')) {
            // Egyptian local: 01274235122 -> +201274235122
            $phone = '+20' . substr($phone, 1);
        }
        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    private function humanMessage(string $message): string
    {
        if (str_contains($message, 'RECAPTCHA')) {
            return 'Verification failed. Please complete the security check and try again.';
        }
        if (str_contains($message, 'TOO_MANY_ATTEMPTS')) {
            return 'Too many attempts. Please try again later.';
        }
        if (str_contains($message, 'INVALID_PHONE')) {
            return 'Invalid phone number format. Use E.164 (e.g. +201274235122).';
        }

        return $message;
    }
}
