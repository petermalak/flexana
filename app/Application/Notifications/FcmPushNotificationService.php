<?php

namespace App\Application\Notifications;

use App\Infrastructure\Persistence\Eloquent\CustomerDeviceTokenModel;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class FcmPushNotificationService
{
    private const FCM_SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function isConfigured(): bool
    {
        return $this->serviceAccount() !== null;
    }

    /**
     * @param  array<string, string|int|bool|null>  $data  FCM data payload (values coerced to strings)
     * @return int Number of devices successfully notified
     */
    public function sendToCustomer(int $customerId, string $title, string $body, array $data = []): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $tokens = CustomerDeviceTokenModel::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->get();

        $sent = 0;
        foreach ($tokens as $device) {
            $result = $this->sendToToken((string) $device->fcm_token, $title, $body, $data);
            if ($result === true) {
                $sent++;
            } elseif ($result === 'unregistered') {
                $device->delete();
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, string|int|bool|null>  $data
     * @return true|'unregistered'|false
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool|string
    {
        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return false;
        }

        $projectId = (string) config('firebase.project_id');
        if ($projectId === '') {
            return false;
        }

        $stringData = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $stringData[(string) $key] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $stringData,
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->successful()) {
                return true;
            }

            $errorCode = (string) ($response->json('error.details.0.errorCode') ?? '');
            $errorStatus = (string) ($response->json('error.status') ?? '');

            if ($errorCode === 'UNREGISTERED' || str_contains($errorStatus, 'NOT_FOUND')) {
                return 'unregistered';
            }

            Log::warning('FCM push failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM push request error', ['error' => $e->getMessage()]);
            report($e);

            return false;
        }
    }

    private function accessToken(): ?string
    {
        $account = $this->serviceAccount();
        if ($account === null) {
            return null;
        }

        $cacheKey = 'firebase_fcm_access_token:' . md5((string) ($account['client_email'] ?? ''));

        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($account): ?string {
            $now = time();
            $jwt = JWT::encode([
                'iss' => $account['client_email'],
                'sub' => $account['client_email'],
                'aud' => $account['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
                'scope' => self::FCM_SCOPE,
            ], $account['private_key'], 'RS256');

            try {
                $response = Http::asForm()->post($account['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                if (! $response->successful()) {
                    Log::error('Firebase OAuth token request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    return null;
                }

                return $response->json('access_token');
            } catch (\Throwable $e) {
                Log::error('Firebase OAuth token error', ['error' => $e->getMessage()]);
                report($e);

                return null;
            }
        });
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri: string}|null
     */
    private function serviceAccount(): ?array
    {
        $path = config('firebase.service_account_json');
        if (! is_string($path) || trim($path) === '' || ! is_readable($path)) {
            return null;
        }

        try {
            $json = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($json)) {
            return null;
        }

        $email = $json['client_email'] ?? null;
        $key = $json['private_key'] ?? null;
        $tokenUri = $json['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        if (! is_string($email) || ! is_string($key) || $email === '' || $key === '') {
            return null;
        }

        return [
            'client_email' => $email,
            'private_key' => $key,
            'token_uri' => is_string($tokenUri) && $tokenUri !== '' ? $tokenUri : 'https://oauth2.googleapis.com/token',
        ];
    }
}
