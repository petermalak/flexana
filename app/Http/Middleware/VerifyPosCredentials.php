<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPosCredentials
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = trim((string) (
            $request->header('Username')
            ?? $request->header('X-Username')
            ?? ''
        ));
        $secret = (string) (
            $request->header('Secret-Key')
            ?? $request->header('X-Secret-Key')
            ?? ''
        );

        if ($username === '' || $secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Username and Secret-Key headers are required.',
            ], 401);
        }

        $apiKey = ApiKey::query()
            ->where(function ($query) use ($username): void {
                $query->where('name', $username)
                    ->orWhere('key_id', $username);
            })
            ->first();

        $valid = false;
        if ($apiKey && $apiKey->isValid()) {
            try {
                $valid = hash_equals($apiKey->getDecryptedSecret(), $secret);
            } catch (\Throwable) {
                $valid = false;
            }
        }

        if (! $valid) {
            Log::warning('Invalid POS credentials', [
                'username' => $username,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid username or secret key.',
            ], 401);
        }

        $clientIp = $request->ip();
        if (! $apiKey->isIpAllowed($clientIp)) {
            Log::warning('POS API used from unauthorized IP', [
                'username' => $username,
                'ip' => $clientIp,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Your IP address is not authorized to use this API.',
            ], 403);
        }

        $apiKey->recordUsage($clientIp);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
