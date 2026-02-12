<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ServeStorageFiles
{
    /**
     * Serve files from storage/app/public when the request path contains /storage/.
     * Runs before routing so it works with any URL shape (e.g. subdirectory).
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Path may be in query string when .htaccess rewrote to index.php (preserves storage path)
        $filePath = $request->query('laravel_storage_path');
        if ($filePath !== null && $filePath !== '') {
            $filePath = ltrim($filePath, '/');
            if (str_contains($filePath, '..')) {
                abort(404);
            }
        } else {
            $path = $request->path();
            if (! str_contains($path, '/storage/') && ! str_starts_with($path, 'storage/')) {
                return $next($request);
            }
            if (! preg_match('#(?:^|/)storage/(.+)$#', $path, $m)) {
                return $next($request);
            }
            $filePath = $m[1];
        }

        if (! Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($filePath), [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
