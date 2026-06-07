<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\SettingModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class ClassReminderSettings
{
    private const GROUP = 'class_reminders';

    public static function autoEnabled(): bool
    {
        return (bool) self::get('enabled', config('sessions.class_reminder_enabled', true));
    }

    public static function emailEnabled(): bool
    {
        return (bool) self::get('email_enabled', config('sessions.class_reminder_email_enabled', true));
    }

    public static function pushEnabled(): bool
    {
        return (bool) self::get('push_enabled', config('sessions.class_reminder_push_enabled', true));
    }

    public static function sendAt(): string
    {
        $value = (string) self::get('send_at', config('sessions.class_reminder_send_at', '09:00'));

        return preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? $value : '09:00';
    }

    /**
     * Whether the scheduled automatic job should run in this invocation (once per calendar day).
     */
    public static function shouldRunAutomaticNow(): bool
    {
        if (! self::autoEnabled()) {
            return false;
        }

        $tz = (string) config('sessions.schedule_timezone', config('app.business_timezone', config('app.timezone')));
        $now = Carbon::now($tz);
        $sendAt = self::sendAt();
        [$hour, $minute] = array_map('intval', explode(':', $sendAt) + [0, 0]);

        if ($now->hour !== $hour || $now->minute < $minute || $now->minute >= $minute + 5) {
            return false;
        }

        $cacheKey = 'class_reminders.automatic_ran.' . $now->format('Y-m-d');
        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, $now->copy()->endOfDay());

        return true;
    }

    public static function save(array $data): void
    {
        SettingModel::setValue(self::GROUP . '.enabled', (bool) ($data['enabled'] ?? false), self::GROUP, 'boolean');
        SettingModel::setValue(self::GROUP . '.email_enabled', (bool) ($data['email_enabled'] ?? false), self::GROUP, 'boolean');
        SettingModel::setValue(self::GROUP . '.push_enabled', (bool) ($data['push_enabled'] ?? false), self::GROUP, 'boolean');
        SettingModel::setValue(self::GROUP . '.send_at', (string) ($data['send_at'] ?? '09:00'), self::GROUP, 'string');

        foreach (['enabled', 'email_enabled', 'push_enabled', 'send_at'] as $key) {
            Cache::forget('settings.' . self::GROUP . '.' . $key);
        }
    }

    /**
     * @return array{enabled: bool, email_enabled: bool, push_enabled: bool, send_at: string}
     */
    public static function formDefaults(): array
    {
        return [
            'enabled' => self::autoEnabled(),
            'email_enabled' => self::emailEnabled(),
            'push_enabled' => self::pushEnabled(),
            'send_at' => self::sendAt(),
        ];
    }

    private static function get(string $key, mixed $default): mixed
    {
        $fullKey = self::GROUP . '.' . $key;

        return Cache::remember('settings.' . $fullKey, 300, function () use ($fullKey, $default) {
            $fromDb = SettingModel::getValue($fullKey, null);

            return $fromDb !== null ? $fromDb : $default;
        });
    }
}
