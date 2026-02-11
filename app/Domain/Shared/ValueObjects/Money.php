<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final class Money
{
    public function __construct(
        public readonly string $currency,
        public readonly int $amountInCents,
    ) {
        if ($this->amountInCents < 0) {
            throw new InvalidArgumentException('Money cannot be negative.');
        }
    }

    public static function fromFloat(float $value, string $currency = 'USD'): self
    {
        return new self($currency, (int) round($value * 100));
    }

    public function toFloat(): float
    {
        return $this->amountInCents / 100;
    }
}

