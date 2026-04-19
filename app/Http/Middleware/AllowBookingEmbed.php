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

        $response->headers->set(
            'Content-Security-Policy',
            'frame-ancestors '.implode(' ', $allowed).';'
        );

        return $response;
    }
}
