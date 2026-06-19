<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class GoogleMapsUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! filter_var($value, FILTER_VALIDATE_URL)) {
            $fail('The :attribute must be a valid URL.');

            return;
        }

        $host = strtolower((string) parse_url($value, PHP_URL_HOST));
        $path = strtolower((string) parse_url($value, PHP_URL_PATH));

        $isGoogleMaps = str_contains($host, 'google.')
            && (str_contains($path, '/maps') || str_contains($host, 'maps.google.'))
            || $host === 'goo.gl' && str_starts_with($path, '/maps')
            || $host === 'maps.app.goo.gl';

        if (! $isGoogleMaps) {
            $fail('The :attribute must be a Google Maps link.');
        }
    }
}
