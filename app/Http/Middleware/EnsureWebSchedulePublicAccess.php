<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebSchedulePublicAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('web_schedule.enabled', true)) {
            return response()->json([
                'success' => false,
                'message' => 'Web schedule endpoint is disabled.',
            ], 404);
        }

        $raw = trim((string) config('web_schedule.allowed_ips', ''));
        if ($raw === '') {
            return $next($request);
        }

        $allowed = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($allowed === []) {
            return $next($request);
        }

        $clientIp = (string) $request->ip();

        foreach ($allowed as $rule) {
            if (IpUtils::checkIp($clientIp, $rule)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Forbidden.',
        ], 403);
    }
}
