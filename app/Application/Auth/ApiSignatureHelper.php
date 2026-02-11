<?php

namespace App\Application\Auth;

/**
 * Helper class for frontend to generate API signatures
 * This can be shared with the frontend team to implement signature generation
 */
class ApiSignatureHelper
{
    /**
     * Generate HMAC signature for API request
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $path API path (e.g., "api/v1/bookings")
     * @param string $queryString URL query string (without ?)
     * @param string $body Request body content
     * @param int $timestamp Unix timestamp
     * @param string $secretKey The API secret key
     * @return string HMAC signature
     */
    public static function generateSignature(
        string $method,
        string $path,
        string $queryString,
        string $body,
        int $timestamp,
        string $secretKey
    ): string {
        // Build the string to sign
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $queryString,
            (string) $timestamp,
            hash('sha256', $body),
        ]);

        // Generate HMAC-SHA256 signature
        return hash_hmac('sha256', $stringToSign, $secretKey);
    }

    /**
     * Generate headers for API request
     * 
     * @param string $keyId The API key ID
     * @param string $secretKey The API secret key
     * @param string $method HTTP method
     * @param string $path API path
     * @param string $queryString URL query string
     * @param string $body Request body
     * @return array Headers array
     */
    public static function generateHeaders(
        string $keyId,
        string $secretKey,
        string $method,
        string $path,
        string $queryString = '',
        string $body = ''
    ): array {
        $timestamp = time();
        $signature = self::generateSignature($method, $path, $queryString, $body, $timestamp, $secretKey);

        return [
            'X-API-Key' => $keyId,
            'X-API-Signature' => $signature,
            'X-API-Timestamp' => (string) $timestamp,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}

