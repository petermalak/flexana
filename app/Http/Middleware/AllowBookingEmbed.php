<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowBookingEmbed
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Some stacks set this header globally; it blocks embedding even with CSP.
        $response->headers->remove('X-Frame-Options');

        $raw = (string) config('booking_embed.frame_ancestors', '');
        $origins = array_values(array_filter(array_map('trim', explode(',', $raw))));

        // If misconfigured, fail closed (do not allow arbitrary embedding).
        if ($origins === []) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none';");

            return $response;
        }

        $allowed = [];
        foreach ($origins as $origin) {
            if (! preg_match('#^https?://#i', $origin)) {
                continue;
            }
            $allowed[] = $origin;
        }

        if ($allowed === []) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none';");

            return $response;
        }

        $existing = (string) $response->headers->get('Content-Security-Policy', '');
        $merged = $this->mergeFrameAncestors($existing, $allowed);
        $response->headers->set('Content-Security-Policy', $merged);

        return $response;
    }

    /**
     * Merge frame-ancestors into an existing CSP header if present.
     *
     * This matters because nginx/Cloudflare sometimes already emits CSP, and browsers
     * intersect multiple CSPs (a too-strict earlier CSP can block embedding).
     */
    private function mergeFrameAncestors(string $existingCsp, array $origins): string
    {
        $existingCsp = trim($existingCsp);
        if ($existingCsp === '') {
            return 'frame-ancestors '.implode(' ', $origins).';';
        }

        $directives = array_values(array_filter(array_map('trim', explode(';', $existingCsp))));
        $out = [];
        $found = false;

        foreach ($directives as $directive) {
            if ($directive === '') {
                continue;
            }
            if (! str_starts_with(strtolower($directive), 'frame-ancestors')) {
                $out[] = $directive;
                continue;
            }

            $parts = preg_split('/\s+/', $directive) ?: [];
            $keyword = strtolower((string) ($parts[0] ?? ''));
            if ($keyword !== 'frame-ancestors') {
                $out[] = $directive;
                continue;
            }

            $existingHosts = array_slice($parts, 1);
            $mergedHosts = array_values(array_unique(array_merge($existingHosts, $origins)));
            $found = true;
            $out[] = 'frame-ancestors '.implode(' ', $mergedHosts);
        }

        if (! $found) {
            $out[] = 'frame-ancestors '.implode(' ', $origins);
        }

        return implode('; ', $out).';';
    }
}
