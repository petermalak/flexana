<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ServeStorageFiles
{
    /**
     * Serve files from storage/app/public when the request is for /storage/*.
     * Checks: query param (from .htaccess), path(), and raw REQUEST_URI.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $filePath = $this->extractStoragePath($request);
        if ($filePath === null) {
            return $next($request);
        }

        if (str_contains($filePath, '..')) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($filePath), [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    private function extractStoragePath(Request $request): ?string
    {
        // 1. Apache env (E=STORAGE_PATH) - set by .htaccess rewrite
        $envPath = $request->server('REDIRECT_STORAGE_PATH') ?: $request->server('STORAGE_PATH');
        if (is_string($envPath) && $envPath !== '') {
            return ltrim($envPath, '/');
        }

        // 2. Query param from .htaccess (index.php?laravel_storage_path=profiles/xxx.png)
        $qp = $request->query('laravel_storage_path');
        if (is_string($qp) && $qp !== '') {
            return ltrim($qp, '/');
        }

        // 3. Laravel path() e.g. "storage/profiles/xxx" or "backend/backend/public/storage/profiles/xxx"
        $path = $request->path();
        if (preg_match('#(?:^|/)storage/(.+)$#', $path, $m)) {
            return $m[1];
        }

        // 4. Raw REQUEST_URI (some servers keep it after rewrite)
        $uri = $request->server('REQUEST_URI');
        if (is_string($uri) && preg_match('#/storage/([^?]+)#', $uri, $m)) {
            return ltrim(urldecode($m[1]), '/');
        }

        return null;
    }
}
