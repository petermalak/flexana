<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

/**
 * Mobile API datetime convention:
 * - Studio-scheduled instants (sessions, bookings, promo validity windows): primary field uses
 *   {@see toBusinessIso8601} (wall clock + offset matching admin); add a sibling `*Utc` field via
 *   {@see toUtcIso8601} when the client needs an absolute instant.
 * - Calendar-only dates (e.g. package expiry day) may use {@see toBusinessDateString}.
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
