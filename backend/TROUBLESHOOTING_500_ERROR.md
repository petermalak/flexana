# Troubleshooting HTTP 500 Error

## Issue
HTTP 500 error on `https://sdhds.net/backend/backend/public/`

## Critical Issues to Check

### 1. Web Server Configuration (MOST LIKELY ISSUE)

The URL shows `/backend/backend/public/` which indicates a **double "backend" in the path**. This suggests:

**Problem**: The web server DocumentRoot is pointing to the wrong location, or there's a subdirectory configuration issue.

**Solution for Apache**:
- DocumentRoot should point to: `/var/www/flexana/backend/public` (or wherever your app is installed)
- NOT to: `/var/www/flexana/backend/backend/public`

**Check your Apache VirtualHost configuration**:
```apache
<VirtualHost *:443>
    ServerName sdhds.net
    DocumentRoot /var/www/flexana/backend/public  # ← Should be this
    
    # NOT this:
    # DocumentRoot /var/www/flexana/backend/backend/public
</VirtualHost>
```

**Solution for Nginx**:
```nginx
server {
    listen 443 ssl;
    server_name sdhds.net;
    root /var/www/flexana/backend/public;  # ← Should be this
    
    # NOT this:
    # root /var/www/flexana/backend/backend/public;
}
```

### 2. Missing .env File or APP_KEY

**Check if .env exists**:
```bash
cd /var/www/flexana/backend
ls -la .env
```

**If missing, create it**:
```bash
cp .env.example .env
php artisan key:generate
```

### 3. File Permissions

**Set correct permissions**:
```bash
cd /var/www/flexana/backend

# Set ownership (adjust user/group as needed)
sudo chown -R www-data:www-data .

# Set directory permissions
sudo find . -type d -exec chmod 755 {} \;

# Set file permissions
sudo find . -type f -exec chmod 644 {} \;

# Set storage and cache permissions (CRITICAL)
sudo chmod -R 775 storage
sudo chmod -R 775 bootstrap/cache
```

### 4. Missing Vendor Directory

**Install dependencies**:
```bash
cd /var/www/flexana/backend
composer install --optimize-autoloader --no-dev
```

### 5. Check Laravel Logs

**View the actual error**:
```bash
tail -f /var/www/flexana/backend/storage/logs/laravel.log
```

Or check the latest log file:
```bash
ls -lt /var/www/flexana/backend/storage/logs/ | head -5
tail -100 /var/www/flexana/backend/storage/logs/laravel-$(date +%Y-%m-%d).log
```

### 6. Clear All Caches

```bash
cd /var/www/flexana/backend
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 7. Check PHP Error Logs

**Apache**:
```bash
tail -f /var/log/apache2/error.log
```

**Nginx + PHP-FPM**:
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/php8.2-fpm.log  # Adjust PHP version
```

### 8. Verify PHP Requirements

```bash
php -v  # Should be 8.2+
php -m  # Check required extensions are loaded
```

Required extensions:
- BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML

### 9. Test Database Connection

```bash
cd /var/www/flexana/backend
php artisan tinker
>>> DB::connection()->getPdo();
```

If this fails, check your `.env` database configuration.

## Quick Diagnostic Steps

1. **Check web server configuration** - Fix the DocumentRoot path
2. **Check Laravel logs** - `storage/logs/laravel.log`
3. **Verify .env exists and has APP_KEY**
4. **Check file permissions** - storage and bootstrap/cache must be writable
5. **Verify vendor directory exists** - Run `composer install` if missing

## Most Common Fix

For your specific error with the double "backend" path:

1. **Fix Apache/Nginx configuration** to point DocumentRoot to the correct location
2. **Restart web server**:
   ```bash
   # Apache
   sudo systemctl restart apache2
   
   # Nginx
   sudo systemctl restart nginx
   sudo systemctl restart php8.2-fpm
   ```

## Still Not Working?

Run the diagnostic script:
```bash
cd /var/www/flexana/backend
php artisan diagnose
```

Or access: `https://sdhds.net/backend/public/diagnose.php` (if you create the diagnostic file)
