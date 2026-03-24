<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Mobile API: emit absolute instants as UTC ISO-8601 so clients can localize correctly.
 * Wall-clock strings (session date/time, emails) use {@see config('app.business_timezone')}.
 */
final class ApiDateTime
{
    public static function toUtcIso8601(Carbon|DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $c = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);

        return $c->utc()->toIso8601String();
    }

    /**
     * Format a stored instant in the studio business timezone (e.g. Egypt) for split date/time fields and emails.
     */
    public static function formatInBusinessTimezone(Carbon|DateTimeInterface|string|null $value, string $format): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $c = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        $tz = (string) config('app.business_timezone');

        return $c->timezone($tz)->format($format);
    }

    public static function toBusinessDateString(Carbon|DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $formatted = self::formatInBusinessTimezone($value, 'Y-m-d');

        return $formatted !== '' ? $formatted : null;
    }

    /**
     * Emit ISO-8601 in business timezone (with offset), useful when API must
     * match admin wall-clock display exactly.
     */
    public static function toBusinessIso8601(Carbon|DateTimeInterface|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $c = $value instanceof Carbon ? $value->copy() : Carbon::parse($value);
        $tz = (string) config('app.business_timezone');

        return $c->timezone($tz)->toIso8601String();
    }
}
