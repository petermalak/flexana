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
     * Build E.164 and stored parts from dial code + national number (signup / profile).
     *
     * @return array{e164: string, countryCode: string, phoneNumber: string}|null
     */
    public static function fromParts(string $countryCode, string $phoneNumber): ?array
    {
        $cc = preg_replace('/\D/', '', $countryCode) ?? '';
        $national = preg_replace('/\D/', '', $phoneNumber) ?? '';

        if ($cc === '' || $national === '') {
            return null;
        }

        if (str_starts_with($national, '0')) {
            $national = substr($national, 1);
        }

        if ($national === '') {
            return null;
        }

        $e164 = '+' . $cc . $national;
        if (! self::isValidE164($e164)) {
            return null;
        }

        return [
            'e164' => $e164,
            'countryCode' => $cc,
            'phoneNumber' => $national,
        ];
    }

    /**
     * Split a stored E.164 number into dial code + national number for API responses.
     *
     * @return array{countryCode: string, phoneNumber: string}|null
     */
    public static function partsFromE164(?string $e164): ?array
    {
        if ($e164 === null || $e164 === '') {
            return null;
        }

        $normalized = self::normalize($e164);
        if ($normalized === '' || ! str_starts_with($normalized, '+')) {
            return null;
        }

        $digits = substr($normalized, 1);
        $defaultCc = (string) config('phone.default_country_code', '20');

        if (str_starts_with($digits, $defaultCc) && strlen($digits) > strlen($defaultCc)) {
            return [
                'countryCode' => $defaultCc,
                'phoneNumber' => substr($digits, strlen($defaultCc)),
            ];
        }

        $knownCodes = config('phone.known_country_calling_codes', [$defaultCc]);
        usort($knownCodes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($knownCodes as $cc) {
            if ($cc === '' || ! str_starts_with($digits, $cc) || strlen($digits) <= strlen($cc)) {
                continue;
            }
            $national = substr($digits, strlen($cc));
            if ($national !== '' && ('+' . $cc . $national) === $normalized) {
                return [
                    'countryCode' => $cc,
                    'phoneNumber' => $national,
                ];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function countryCodeValidationRules(bool $required = false): array
    {
        $rules = ['string', 'max:4', 'regex:/^\+?[1-9]\d{0,3}$/'];

        return $required ? array_merge(['required'], $rules) : array_merge(['nullable'], $rules);
    }

    /**
     * @return array<int, string>
     */
    public static function nationalNumberValidationRules(bool $required = false): array
    {
        $rules = ['string', 'max:15', 'regex:/^\d{4,14}$/'];

        return $required ? array_merge(['required'], $rules) : array_merge(['nullable'], $rules);
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
