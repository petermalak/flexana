<?php

namespace App\Application\Auth;

use App\Infrastructure\Auth\FirebaseTokenVerifier;
use App\Models\User;
use Illuminate\Support\Str;

final class FirebaseAuthService
{
    public function __construct(
        private readonly FirebaseTokenVerifier $verifier,
    ) {
    }

    public function authenticate(string $token): User
    {
        $decoded = $this->verifier->verify($token);

        $user = User::query()->firstOrNew([
            'firebase_uid' => $decoded->uid,
        ]);

        if (! $user->exists) {
            $user->uuid = (string) Str::uuid();
        }

        $user->fill([
            'name' => $decoded->name,
            'email' => $decoded->email,
            'avatar_url' => $decoded->picture,
            'status' => 'active',
            'email_verified_at' => now(),
            'last_login_at' => now(),
        ])->save();

        if ($role = data_get($decoded->claims, 'role')) {
            $user->syncRoles([$role]);
        }

        return $user;
    }
}

