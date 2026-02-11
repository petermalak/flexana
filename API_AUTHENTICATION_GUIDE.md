# API Authentication Guide

This guide explains how to authenticate API requests to the Flexana backend using API keys and HMAC signatures.

## Overview

The Flexana API uses a two-part authentication system:
1. **API Key ID** - Public identifier sent in `X-API-Key` header
2. **HMAC Signature** - Cryptographic signature generated from request details

This ensures:
- ✅ Only authorized clients can access the API
- ✅ Request integrity (prevents tampering)
- ✅ Replay attack prevention (timestamp validation)
- ✅ Optional IP whitelisting

## Generating API Keys

### Using Artisan Command

```bash
php artisan api:generate-key "Frontend App"
```

With IP restrictions:
```bash
php artisan api:generate-key "Frontend App" --ips=192.168.1.100,10.0.0.50
```

With expiration:
```bash
php artisan api:generate-key "Frontend App" --expires=2025-12-31
```

### Output

The command will display:
- **Key ID**: Public identifier (starts with `fk_`)
- **Secret Key**: Private key for signature generation (⚠️ **SAVE THIS - shown only once!**)

Example:
```
Key ID: fk_a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
Secret Key: xYz123AbC456DeF789GhI012JkL345MnO678PqR901StU234VwX567YzA890
```

## Making Authenticated Requests

### Required Headers

Every API request must include:

1. **X-API-Key**: Your API key ID
2. **X-API-Signature**: HMAC-SHA256 signature
3. **X-API-Timestamp**: Unix timestamp (seconds since epoch)

### Signature Generation

The signature is generated from:
```
METHOD\nPATH\nQUERY_STRING\nTIMESTAMP\nBODY_HASH
```

Where:
- `METHOD`: HTTP method (uppercase: GET, POST, PUT, DELETE)
- `PATH`: API path (e.g., `api/v1/bookings`)
- `QUERY_STRING`: URL query parameters (without `?`)
- `TIMESTAMP`: Unix timestamp as string
- `BODY_HASH`: SHA256 hash of request body (empty string for GET requests)

### Example: JavaScript/TypeScript

```javascript
class FlexanaApiClient {
    constructor(keyId, secretKey, baseUrl) {
        this.keyId = keyId;
        this.secretKey = secretKey;
        this.baseUrl = baseUrl;
    }

    generateSignature(method, path, queryString, body, timestamp) {
        const crypto = require('crypto');
        
        const stringToSign = [
            method.toUpperCase(),
            path,
            queryString || '',
            timestamp.toString(),
            crypto.createHash('sha256').update(body || '').digest('hex')
        ].join('\n');

        return crypto.createHmac('sha256', this.secretKey)
            .update(stringToSign)
            .digest('hex');
    }

    async request(method, path, body = null, queryParams = {}) {
        const timestamp = Math.floor(Date.now() / 1000);
        const queryString = new URLSearchParams(queryParams).toString();
        const bodyString = body ? JSON.stringify(body) : '';
        
        const signature = this.generateSignature(
            method,
            path,
            queryString,
            bodyString,
            timestamp
        );

        const headers = {
            'X-API-Key': this.keyId,
            'X-API-Signature': signature,
            'X-API-Timestamp': timestamp.toString(),
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        };

        const url = `${this.baseUrl}/${path}${queryString ? '?' + queryString : ''}`;
        
        const response = await fetch(url, {
            method,
            headers,
            body: bodyString || undefined,
        });

        return response.json();
    }

    // Convenience methods
    async get(path, queryParams = {}) {
        return this.request('GET', path, null, queryParams);
    }

    async post(path, body) {
        return this.request('POST', path, body);
    }

    async put(path, body) {
        return this.request('PUT', path, body);
    }

    async delete(path) {
        return this.request('DELETE', path);
    }
}

// Usage
const client = new FlexanaApiClient(
    'fk_your_key_id_here',
    'your_secret_key_here',
    'https://api.flexana.com'
);

// Make a request
const bookings = await client.get('api/v1/bookings', { per_page: 25 });
```

### Example: PHP

```php
<?php

class FlexanaApiClient
{
    private string $keyId;
    private string $secretKey;
    private string $baseUrl;

    public function __construct(string $keyId, string $secretKey, string $baseUrl)
    {
        $this->keyId = $keyId;
        $this->secretKey = $secretKey;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function generateSignature(
        string $method,
        string $path,
        string $queryString,
        string $body,
        int $timestamp
    ): string {
        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $queryString,
            (string) $timestamp,
            hash('sha256', $body),
        ]);

        return hash_hmac('sha256', $stringToSign, $this->secretKey);
    }

    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $queryParams = []
    ): array {
        $timestamp = time();
        $queryString = http_build_query($queryParams);
        $bodyString = $body ? json_encode($body) : '';

        $signature = $this->generateSignature(
            $method,
            $path,
            $queryString,
            $bodyString,
            $timestamp
        );

        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $this->keyId,
                'X-API-Signature: ' . $signature,
                'X-API-Timestamp: ' . $timestamp,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => $bodyString ?: null,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("API request failed: HTTP $httpCode - $response");
        }

        return json_decode($response, true);
    }
}

// Usage
$client = new FlexanaApiClient(
    'fk_your_key_id_here',
    'your_secret_key_here',
    'https://api.flexana.com'
);

$bookings = $client->request('GET', 'api/v1/bookings', null, ['per_page' => 25]);
```

### Example: Python

```python
import hmac
import hashlib
import time
import requests
from urllib.parse import urlencode

class FlexanaApiClient:
    def __init__(self, key_id, secret_key, base_url):
        self.key_id = key_id
        self.secret_key = secret_key
        self.base_url = base_url.rstrip('/')

    def generate_signature(self, method, path, query_string, body, timestamp):
        string_to_sign = '\n'.join([
            method.upper(),
            path,
            query_string or '',
            str(timestamp),
            hashlib.sha256((body or '').encode()).hexdigest()
        ])

        return hmac.new(
            self.secret_key.encode(),
            string_to_sign.encode(),
            hashlib.sha256
        ).hexdigest()

    def request(self, method, path, body=None, query_params=None):
        timestamp = int(time.time())
        query_string = urlencode(query_params or {})
        body_string = json.dumps(body) if body else ''

        signature = self.generate_signature(
            method,
            path,
            query_string,
            body_string,
            timestamp
        )

        url = f"{self.base_url}/{path.lstrip('/')}"
        if query_string:
            url += f"?{query_string}"

        headers = {
            'X-API-Key': self.key_id,
            'X-API-Signature': signature,
            'X-API-Timestamp': str(timestamp),
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        }

        response = requests.request(
            method,
            url,
            headers=headers,
            json=body if body else None
        )

        response.raise_for_status()
        return response.json()

# Usage
client = FlexanaApiClient(
    'fk_your_key_id_here',
    'your_secret_key_here',
    'https://api.flexana.com'
)

bookings = client.request('GET', 'api/v1/bookings', query_params={'per_page': 25})
```

## Security Best Practices

1. **Never expose secret keys** in client-side code or public repositories
2. **Store secrets securely** using environment variables or secure vaults
3. **Rotate keys regularly** - Generate new keys and revoke old ones
4. **Use IP whitelisting** for additional security
5. **Set expiration dates** for temporary access
6. **Monitor usage** - Check `last_used_at` and `last_ip_address` in the database

## Error Responses

### 401 Unauthorized

```json
{
    "error": "Missing API key",
    "message": "X-API-Key header is required"
}
```

```json
{
    "error": "Invalid signature",
    "message": "The request signature is invalid"
}
```

```json
{
    "error": "Request expired",
    "message": "The request timestamp is too old or too far in the future"
}
```

### 403 Forbidden

```json
{
    "error": "IP address not allowed",
    "message": "Your IP address is not authorized to use this API key"
}
```

## Managing API Keys

### Viewing Keys

Check the `api_keys` table in your database or create a Filament resource to manage them.

### Revoking Keys

Set `is_active = false` in the database, or delete the key record.

### Updating IP Restrictions

Update the `allowed_ips` JSON field in the database.

## Testing

Use the Postman collection with the new authentication method. Update the collection variables:
- `api_key_id`: Your key ID
- `api_secret_key`: Your secret key

The Postman collection includes pre-request scripts to automatically generate signatures.

