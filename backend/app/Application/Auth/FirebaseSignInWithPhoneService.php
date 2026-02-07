<?php

namespace App\Application\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FirebaseSignInWithPhoneService
{
    private const SIGN_IN_WITH_PHONE_URL = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPhoneNumber';

    public function __construct(
        private readonly string $apiKey,
    ) {
    }

    /**
     * Exchange sessionInfo + code for Firebase ID token (after user received SMS and entered code).
     *
     * @return array{success: bool, message: string, idToken?: string}
     */
    public function signIn(string $sessionInfo, string $code): array
    {
        $body = [
            'sessionInfo' => $sessionInfo,
            'code' => $code,
        ];

        try {
            $response = Http::timeout(15)
                ->post(self::SIGN_IN_WITH_PHONE_URL . '?key=' . $this->apiKey, $body);

            $data = $response->json();

            if (! $response->successful()) {
                $message = $data['error']['message'] ?? $response->body();
                Log::warning('Firebase signInWithPhoneNumber failed', [
                    'status' => $response->status(),
                    'message' => $message,
                ]);

                return [
                    'success' => false,
                    'message' => $this->humanMessage($message),
                ];
            }

            $idToken = $data['idToken'] ?? $data['temporaryProof'] ?? null;
            if (empty($idToken)) {
                return ['success' => false, 'message' => 'Firebase did not return an ID token.'];
            }

            return [
                'success' => true,
                'message' => 'Verified.',
                'idToken' => $idToken,
            ];
        } catch (\Throwable $e) {
            Log::warning('Firebase signInWithPhoneNumber request failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => 'Verification failed. Please try again.',
            ];
        }
    }

    private function humanMessage(string $message): string
    {
        if (str_contains($message, 'INVALID_CODE') || str_contains($message, 'SESSION_EXPIRED')) {
            return 'Invalid or expired code. Please request a new one.';
        }
        if (str_contains($message, 'TOO_MANY_ATTEMPTS')) {
            return 'Too many attempts. Please try again later.';
        }

        return $message;
    }
}
