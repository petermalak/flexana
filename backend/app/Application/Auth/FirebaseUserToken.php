<?php

namespace App\Application\Auth;

final class FirebaseUserToken
{
    public function __construct(
        public readonly string $uid,
        public readonly ?string $email,
        public readonly ?string $name,
        public readonly ?string $picture,
        public readonly array $claims,
    ) {
    }
}

