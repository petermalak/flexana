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
                $error = $data['error'] ?? [];
                $rawMessage = $error['message'] ?? $response->body();
                $errorCode = $error['code'] ?? $error['status'] ?? null;

                Log::warning('Firebase sendVerificationCode failed', [
                    'phone' => $phoneNumber,
                    'http_status' => $response->status(),
                    'firebase_error_code' => $errorCode,
                    'firebase_message' => $rawMessage,
                    'response_body' => $data,
                ]);

                return [
                    'success' => false,
                    'message' => $this->humanMessage($errorCode, is_string($rawMessage) ? $rawMessage : null),
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

    /**
     * Map Firebase error code/message to a user- and developer-friendly message.
     * See docs/FIREBASE_PHONE_ANDROID_SETUP.md for how to fix Android/Play Integrity issues.
     */
    private function humanMessage(mixed $errorCode, ?string $message): string
    {
        $code = is_numeric($errorCode) ? (int) $errorCode : null;
        $msg = $message ?? '';

        // Known Firebase/Identity Platform error codes for sendVerificationCode
        if ($code === 18002) {
            return 'App not recognized by Play. Add your app SHA-256 in Firebase Console (Project settings > Your apps > Android > Add fingerprint). For debug builds use your debug keystore SHA-256.';
        }
        if ($code === 17028) {
            return 'Invalid Play Integrity token. Ensure the app is signed with the same key whose SHA-256 is registered in Firebase, and that Play Integrity API is enabled for your Google Cloud project.';
        }
        if ($code === 400 || $code === 403) {
            if (str_contains($msg, 'RECAPTCHA')) {
                return 'Verification failed. Please complete the security check and try again.';
            }
            if (str_contains($msg, 'TOO_MANY_ATTEMPTS')) {
                return 'Too many attempts. Please try again later.';
            }
            if (str_contains($msg, 'INVALID_PHONE')) {
                return 'Invalid phone number format. Use E.164 (e.g. +201274235122).';
            }
            if (str_contains($msg, 'Internal error') || str_contains($msg, 'INTERNAL')) {
                return 'Verification service error. On Android: add your app SHA-256 in Firebase Console (Project settings > Your apps) and use the same API key as your app. See docs/FIREBASE_PHONE_ANDROID_SETUP.md.';
            }
            if (str_contains($msg, 'INVALID_APP_CREDENTIAL') || str_contains($msg, 'APP_NOT_VERIFIED')) {
                return 'App not verified. Add your app SHA-256 (Android) or bundle ID (iOS) in Firebase Console.';
            }
        }

        if ($msg === '') {
            return 'Verification service error. Please try again.';
        }
        return $msg;
    }
}
