<?php

namespace App\Infrastructure\Auth;

use App\Application\Auth\FirebaseUserToken;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class FirebaseTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly HttpFactory $http,
        private readonly string $projectId,
    ) {
    }

    public function verify(string $jwt): FirebaseUserToken
    {
        $decoded = JWT::decode($jwt, $this->buildKeySet(), ['RS256']);

        if (($decoded->aud ?? null) !== $this->projectId) {
            throw new RuntimeException('Invalid Firebase audience.');
        }

        $expectedIssuer = sprintf('https://securetoken.google.com/%s', $this->projectId);

        if (($decoded->iss ?? null) !== $expectedIssuer) {
            throw new RuntimeException('Invalid Firebase issuer.');
        }

        if (! isset($decoded->sub)) {
            throw new RuntimeException('Firebase token missing subject claim.');
        }

        return new FirebaseUserToken(
            uid: $decoded->sub,
            email: $decoded->email ?? null,
            name: $decoded->name ?? null,
            picture: $decoded->picture ?? null,
            claims: (array) $decoded,
        );
    }

    private function buildKeySet(): array
    {
        $keys = $this->cache->remember('firebase_jwks', now()->addHours(1), function () {
            $response = $this->http->timeout(5)->get(self::JWKS_URL);

            $response->throwUnlessStatus(200);

            return $response->json();
        });

        if (! is_array($keys) || empty($keys['keys'])) {
            Log::error('Unable to load Firebase JWKS.', ['keys' => $keys]);
            throw new RuntimeException('Unable to load Firebase public keys.');
        }

        return JWK::parseKeySet($keys);
    }
}

