<?php

namespace App\Support;

final class FirebaseServiceAccountPath
{
    /**
     * Resolve Firebase service account JSON path for any environment.
     *
     * - Empty / unset → storage/app/firebase-service-account.json (project-relative)
     * - Relative path → resolved from Laravel base_path() (e.g. storage/app/firebase-service-account.json)
     * - Absolute path → used as-is (optional override on a specific server)
     */
    public static function resolve(?string $configuredPath = null): string
    {
        $path = trim((string) ($configuredPath ?? ''));

        if ($path === '') {
            return storage_path('app/firebase-service-account.json');
        }

        if (self::isAbsolute($path)) {
            return $path;
        }

        return base_path($path);
    }

    private static function isAbsolute(string $path): bool
    {
        if (str_starts_with($path, '/')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
