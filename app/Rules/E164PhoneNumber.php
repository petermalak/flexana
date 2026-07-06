<?php

namespace App\Rules;

use App\Support\PhoneNumberNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class E164PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumberNormalizer::isValidE164($value)) {
            $fail('The :attribute must be a valid international phone number in E.164 format (e.g. +201274235122 or +447911123456).');
        }
    }
}
