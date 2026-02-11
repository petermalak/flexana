# API Authentication Setup Complete ✅

## What Was Implemented

A secure API authentication system has been added to protect your Flexana backend from unauthorized access. The system uses:

1. **API Key ID** - Public identifier
2. **HMAC Signature** - Cryptographic signature to verify request integrity
3. **Timestamp Validation** - Prevents replay attacks (5-minute window)
4. **Optional IP Whitelisting** - Restrict access to specific IP addresses
5. **Key Expiration** - Optional expiration dates for temporary access

## Quick Start

### 1. Generate Your First API Key

```bash
php artisan api:generate-key "Frontend App"
```

This will output:
- **Key ID**: `fk_xxxxxxxxxxxxx` (public, can be shared)
- **Secret Key**: `xxxxxxxxxxxxx` (private, keep secure!)

⚠️ **IMPORTANT**: Save the secret key immediately - it's shown only once!

### 2. Configure Postman

1. Open the `Flexana_API_Collection.postman_collection.json` in Postman
2. Click on the collection name
3. Go to **Variables** tab
4. Set:
   - `api_key_id`: Your Key ID (e.g., `fk_abc123...`)
   - `api_secret_key`: Your Secret Key
5. The pre-request script will automatically generate signatures for all requests

### 3. Test an API Request

Try making a GET request to `/api/v1/events`. The signature will be generated automatically.

## Files Created

### Database
- ✅ Migration: `create_api_keys_table`
- ✅ Model: `App\Models\ApiKey`

### Middleware
- ✅ `App\Http\Middleware\VerifyApiSignature` - Validates API keys and signatures

### Commands
- ✅ `php artisan api:generate-key` - Generate new API keys

### Documentation
- ✅ `API_AUTHENTICATION_GUIDE.md` - Complete guide with code examples
- ✅ `AUTHENTICATION_SETUP.md` - This file

### Updated Files
- ✅ `routes/api.php` - Now uses `api.signature` middleware
- ✅ `bootstrap/app.php` - Registered middleware alias
- ✅ `Flexana_API_Collection.postman_collection.json` - Updated with signature auth

## How It Works

### Request Flow

1. Frontend generates a signature using:
   - HTTP Method
   - API Path
   - Query String
   - Unix Timestamp
   - SHA256 hash of request body

2. Frontend sends headers:
   - `X-API-Key`: Your Key ID
   - `X-API-Signature`: Generated HMAC signature
   - `X-API-Timestamp`: Current Unix timestamp

3. Backend verifies:
   - Key exists and is active
   - Key not expired
   - IP address allowed (if restricted)
   - Timestamp within 5-minute window
   - Signature matches request

### Security Features

- ✅ **Request Integrity**: HMAC signature prevents tampering
- ✅ **Replay Protection**: Timestamp validation (5-minute window)
- ✅ **IP Restrictions**: Optional whitelist
- ✅ **Key Expiration**: Optional expiration dates
- ✅ **Usage Tracking**: Last used timestamp and IP logged

## Managing API Keys

### View All Keys

```bash
php artisan tinker
>>> App\Models\ApiKey::all();
```

### Deactivate a Key

```bash
php artisan tinker
>>> $key = App\Models\ApiKey::where('key_id', 'fk_xxx')->first();
>>> $key->update(['is_active' => false]);
```

### Delete a Key

```bash
php artisan tinker
>>> App\Models\ApiKey::where('key_id', 'fk_xxx')->delete();
```

### Update IP Restrictions

```bash
php artisan tinker
>>> $key = App\Models\ApiKey::where('key_id', 'fk_xxx')->first();
>>> $key->update(['allowed_ips' => ['192.168.1.100', '10.0.0.50']]);
```

## Frontend Integration

See `API_AUTHENTICATION_GUIDE.md` for complete code examples in:
- JavaScript/TypeScript
- PHP
- Python

## Testing

1. Generate a test key:
   ```bash
   php artisan api:generate-key "Test Key"
   ```

2. Use Postman to test:
   - Set collection variables
   - Make a request
   - Check that it works

3. Test invalid scenarios:
   - Wrong signature → Should return 401
   - Expired timestamp → Should return 401
   - Invalid key → Should return 401
   - Wrong IP (if restricted) → Should return 403

## Next Steps

1. ✅ Generate API keys for your frontend applications
2. ✅ Share the secret keys securely with your frontend team
3. ✅ Implement signature generation in your frontend code
4. ✅ Test all API endpoints
5. ✅ Monitor API key usage in the database

## Troubleshooting

### "Missing API key" error
- Check that `X-API-Key` header is set
- Verify the key exists in the database

### "Invalid signature" error
- Verify the signature generation matches the backend algorithm
- Check that all components (method, path, query, timestamp, body) are included
- Ensure the secret key is correct

### "Request expired" error
- Check system clock synchronization
- Timestamp must be within 5 minutes of server time

### "IP address not allowed" error
- Check `allowed_ips` field in database
- Remove IP restriction or add your IP to the whitelist

## Security Best Practices

1. **Never commit secrets to version control**
2. **Use environment variables** for secret keys
3. **Rotate keys regularly** (generate new, revoke old)
4. **Use IP whitelisting** for production
5. **Set expiration dates** for temporary access
6. **Monitor usage** - Check `last_used_at` and `last_ip_address`
7. **Revoke compromised keys immediately**

## Support

For questions or issues:
1. Check `API_AUTHENTICATION_GUIDE.md` for detailed examples
2. Review the middleware code: `app/Http/Middleware/VerifyApiSignature.php`
3. Check Laravel logs: `storage/logs/laravel.log`

