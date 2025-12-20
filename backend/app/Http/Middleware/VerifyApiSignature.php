<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get API key ID from header
        $keyId = $request->header('X-API-Key');
        
        if (!$keyId) {
            return response()->json([
                'error' => 'Missing API key',
                'message' => 'X-API-Key header is required',
            ], 401);
        }

        // Find the API key
        $apiKey = ApiKey::where('key_id', $keyId)->first();

        if (!$apiKey) {
            Log::warning('Invalid API key attempted', ['key_id' => $keyId, 'ip' => $request->ip()]);
            return response()->json([
                'error' => 'Invalid API key',
                'message' => 'The provided API key is not valid',
            ], 401);
        }

        // Check if key is valid (active and not expired)
        if (!$apiKey->isValid()) {
            return response()->json([
                'error' => 'API key is not active',
                'message' => 'The API key has been deactivated or has expired',
            ], 401);
        }

        // Check IP restriction if set
        $clientIp = $request->ip();
        if (!$apiKey->isIpAllowed($clientIp)) {
            Log::warning('API key used from unauthorized IP', [
                'key_id' => $keyId,
                'ip' => $clientIp,
                'allowed_ips' => $apiKey->allowed_ips,
            ]);
            return response()->json([
                'error' => 'IP address not allowed',
                'message' => 'Your IP address is not authorized to use this API key',
            ], 403);
        }

        // Verify HMAC signature
        $signature = $request->header('X-API-Signature');
        $timestamp = $request->header('X-API-Timestamp');

        if (!$signature || !$timestamp) {
            return response()->json([
                'error' => 'Missing signature or timestamp',
                'message' => 'X-API-Signature and X-API-Timestamp headers are required',
            ], 401);
        }

        // Verify timestamp (prevent replay attacks - allow 5 minutes window)
        $requestTime = (int) $timestamp;
        $currentTime = time();
        $timeDifference = abs($currentTime - $requestTime);

        if ($timeDifference > 300) { // 5 minutes
            return response()->json([
                'error' => 'Request expired',
                'message' => 'The request timestamp is too old or too far in the future',
            ], 401);
        }

        // Reconstruct the signature
        $method = $request->method();
        $path = $request->path();
        $queryString = $request->getQueryString() ?? '';
        $body = $request->getContent();
        
        // Build the string to sign
        $stringToSign = implode("\n", [
            $method,
            $path,
            $queryString,
            $timestamp,
            hash('sha256', $body),
        ]);

        // We need the plain secret key to verify, but we only store hashed
        // So we'll need to pass it differently or use a different approach
        // For now, we'll store a separate verification token or use a different method
        
        // Alternative: Store a separate verification key (not hashed) for HMAC
        // Or: Use the key_id + secret combination stored securely
        
        // For security, we'll need to retrieve the secret from a secure store
        // or use a different approach. Let's use a separate "verification_secret" field
        // that's encrypted instead of hashed (since HMAC needs the original value)
        
        // For now, let's check if we have a way to verify
        // We'll need to modify the approach - store encrypted secret instead of hashed
        // But for MVP, let's use a simpler approach with a separate verification field
        
        // Actually, for HMAC verification, we need the original secret
        // So we should encrypt it, not hash it
        // Let's update the model to handle this properly
        
        // For now, let's implement a working solution:
        // We'll need to decrypt the secret to verify HMAC
        $expectedSignature = $this->generateSignature($stringToSign, $apiKey);
        
        // Use hash_equals for timing-safe comparison
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('Invalid API signature', [
                'key_id' => $keyId,
                'ip' => $clientIp,
                'expected' => substr($expectedSignature, 0, 10) . '...',
                'received' => substr($signature, 0, 10) . '...',
            ]);
            
            return response()->json([
                'error' => 'Invalid signature',
                'message' => 'The request signature is invalid',
            ], 401);
        }

        // Record usage
        $apiKey->recordUsage($clientIp);

        // Attach API key to request for use in controllers
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }

    /**
     * Generate HMAC signature for verification
     * Note: This requires access to the original secret, so we need to store it encrypted
     */
    private function generateSignature(string $stringToSign, ApiKey $apiKey): string
    {
        // Get the decrypted secret key for HMAC verification
        $secret = $apiKey->getDecryptedSecret();
        
        return hash_hmac('sha256', $stringToSign, $secret);
    }
}
