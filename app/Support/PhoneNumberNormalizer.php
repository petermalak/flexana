<?php

namespace App\Support;

/**
 * Canonical phone format for customer matching (E.164).
 * Egyptian local numbers (01…) are mapped to +20; other countries must include +country code.
 */
final class PhoneNumberNormalizer
{
    /** E.164 allows up to 15 digits after the leading +. */
    public const E164_MAX_LENGTH = 16;

    public static function normalize(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', preg_replace('/\s+/', '', $phone) ?? '') ?? '';
        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '0') && self::isDefaultCountryLocalNumber($phone)) {
            $countryCode = (string) config('phone.default_country_code', '20');

            return '+' . $countryCode . substr($phone, 1);
        }

        return '+' . $phone;
    }

    public static function normalizeNullable(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $n = self::normalize($phone);

        return $n === '' ? null : $n;
    }

    public static function isValidE164(string $phone): bool
    {
        $phone = preg_replace('/\s+/', '', $phone) ?? '';

        return (bool) preg_match('/^\+[1-9]\d{6,14}$/', $phone);
    }

    /**
     * @return array<int, string>
     */
    public static function validationRules(bool $required = true): array
    {
        $rules = ['string', 'max:' . self::E164_MAX_LENGTH, new \App\Rules\E164PhoneNumber];

        return $required ? array_merge(['required'], $rules) : array_merge(['nullable'], $rules);
    }

    private static function isDefaultCountryLocalNumber(string $phone): bool
    {
        $patterns = config('phone.default_country_local_patterns', []);

        foreach ($patterns as $pattern) {
            if (is_string($pattern) && preg_match($pattern, $phone)) {
                return true;
            }
        }

        return false;
    }
}
