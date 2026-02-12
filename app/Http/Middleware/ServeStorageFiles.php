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
        $path = $request->path();

        if (! str_contains($path, '/storage/') && ! str_starts_with($path, 'storage/')) {
            return $next($request);
        }

        // Extract path after the last "storage/" segment
        if (preg_match('#(?:^|/)storage/(.+)$#', $path, $m)) {
            $filePath = $m[1];
        } else {
            return $next($request);
        }

        if (! Storage::disk('public')->exists($filePath)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($filePath), [
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
