<?php

namespace App\Support;

/**
 * Canonical phone format for customer matching (same rules as PhoneVerificationService).
 * Keeps signup, SMS verification, and Firestore migration aligned.
 */
final class PhoneNumberNormalizer
{
    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        if ($phone === '') {
            return '';
        }
        if (str_starts_with($phone, '0')) {
            $phone = '+20' . substr($phone, 1);
        }
        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }

    public static function normalizeNullable(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $n = self::normalize($phone);

        return $n === '' ? null : $n;
    }
}
