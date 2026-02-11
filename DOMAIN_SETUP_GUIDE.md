# Domain Setup Guide for Production

## Overview

This guide explains how to make your domain point to the Laravel `public` folder when deployed to `/domains/admin-panel-flexana-egypt/public_html/`.

---

## Understanding the Directory Structure

### Laravel Application Structure

```
/domains/admin-panel-flexana-egypt/
├── flexana/                    # Your Laravel application (OUTSIDE web root - SECURE)
│   ├── app/                    # Application code
│   ├── bootstrap/              # Bootstrap files
│   ├── config/                 # Configuration files
│   ├── database/               # Migrations and seeds
│   ├── public/                 # Web-accessible files (THIS is your actual web root)
│   │   ├── index.php           # Entry point
│   │   ├── .htaccess           # URL rewriting rules
│   │   ├── css/                # Compiled CSS
│   │   ├── js/                 # Compiled JavaScript
│   │   └── images/             # Public images
│   ├── resources/              # Raw assets and views
│   ├── routes/                 # Route definitions
│   ├── storage/                # Logs, cache, uploads (PRIVATE)
│   ├── vendor/                 # PHP dependencies (PRIVATE)
│   └── .env                    # Environment config (PRIVATE - NEVER PUBLIC)
└── public_html/                # Your web server root (what the domain points to)
```

### Security Note

**IMPORTANT:** Your `.env` file, `vendor/`, `storage/`, and other sensitive directories should NEVER be directly accessible via the web. This is why we keep the Laravel app outside `public_html` and only expose the `public/` folder.

---

## Solution 1: Symlink Method (RECOMMENDED)

### What is a Symlink?

A symbolic link (symlink) is like a shortcut that makes `public_html` point directly to `flexana/public`.

### How to Create

```bash
# Remove existing public_html (backup first if needed)
cd /domains/admin-panel-flexana-egypt/
mv public_html public_html_backup

# Create symlink
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Verify
ls -la public_html
# Should show: public_html -> /domains/admin-panel-flexana-egypt/flexana/public
```

### Advantages
✅ Clean and secure
✅ Easy to update (just pull new code)
✅ No file duplication
✅ Laravel structure stays intact

### When Your Domain is Accessed

```
User visits: https://admin-panel-flexana-egypt.com
           ↓
Web server looks for: /domains/admin-panel-flexana-egypt/public_html/
           ↓
Symlink redirects to: /domains/admin-panel-flexana-egypt/flexana/public/
           ↓
Laravel index.php loads the application
```

### Troubleshooting Symlinks

**If symlinks don't work:**

1. **Check if server supports symlinks:**
   ```bash
   ln -s /tmp/test /tmp/test_link && echo "Symlinks work!" || echo "Symlinks disabled"
   rm /tmp/test_link /tmp/test 2>/dev/null
   ```

2. **Check Apache configuration** (requires root access):
   - Ensure `FollowSymLinks` is enabled in Apache config
   - In your virtual host or `.htaccess`:
     ```apache
     Options +FollowSymLinks
     ```

3. **cPanel Configuration:**
   - Some shared hosting disables symlinks for security
   - Contact your hosting provider to enable them
   - Or use Solution 2 below

---

## Solution 2: Move Public Contents (If Symlinks Don't Work)

If your hosting provider doesn't support symlinks, you can move the contents of the `public` folder.

### Directory Structure After Setup

```
/domains/admin-panel-flexana-egypt/
├── flexana/                    # Laravel application (OUTSIDE web root)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/                 # Original public folder (keep for reference)
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   └── .env
└── public_html/                # Copy of public folder with modified index.php
    ├── index.php               # MODIFIED to point to ../flexana/
    ├── .htaccess
    ├── css/
    ├── js/
    └── images/
```

### Step-by-Step Setup

#### 1. Copy Public Folder Contents

```bash
cd /domains/admin-panel-flexana-egypt/

# Backup existing public_html if needed
mv public_html public_html_backup

# Copy public folder contents
cp -r flexana/public public_html
```

#### 2. Update index.php

Edit `/domains/admin-panel-flexana-egypt/public_html/index.php`:

**Original:**
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
```

**Updated (change paths to point to flexana/):**
```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../flexana/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../flexana/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../flexana/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

**What Changed:**
- `__DIR__.'/../storage/` → `__DIR__.'/../flexana/storage/`
- `__DIR__.'/../vendor/` → `__DIR__.'/../flexana/vendor/`
- `__DIR__.'/../bootstrap/` → `__DIR__.'/../flexana/bootstrap/`

#### 3. Verify .htaccess Exists

Ensure `/domains/admin-panel-flexana-egypt/public_html/.htaccess` exists with:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle X-XSRF-Token Header
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### Advantages & Disadvantages

**Advantages:**
✅ Works on all hosting providers
✅ No symlink support needed

**Disadvantages:**
❌ Need to copy assets after each build
❌ More maintenance when updating
❌ File duplication

### Keeping Assets Updated

After running `npm run build` in your Laravel app, you need to copy the updated assets:

```bash
# After building assets in flexana/
cd /domains/admin-panel-flexana-egypt/

# Copy updated CSS
cp -r flexana/public/css public_html/

# Copy updated JS
cp -r flexana/public/js public_html/

# Copy any new images
cp -r flexana/public/images public_html/
```

**Tip:** Create a simple script to automate this:

```bash
#!/bin/bash
# update-assets.sh

cd /domains/admin-panel-flexana-egypt/
cp -r flexana/public/css public_html/
cp -r flexana/public/js public_html/
cp -r flexana/public/images public_html/
echo "Assets updated!"
```

---

## Solution 3: Document Root Configuration (If You Have Server Access)

If you have root/admin access to your server (VPS/dedicated), you can change the document root directly.

### Apache Virtual Host

Edit your Apache virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName admin-panel-flexana-egypt.com
    ServerAlias www.admin-panel-flexana-egypt.com
    
    # Point directly to Laravel public folder
    DocumentRoot /domains/admin-panel-flexana-egypt/flexana/public

    <Directory /domains/admin-panel-flexana-egypt/flexana/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/flexana-error.log
    CustomLog ${APACHE_LOG_DIR}/flexana-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName admin-panel-flexana-egypt.com
    ServerAlias www.admin-panel-flexana-egypt.com
    
    # Point directly to Laravel public folder
    DocumentRoot /domains/admin-panel-flexana-egypt/flexana/public

    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key

    <Directory /domains/admin-panel-flexana-egypt/flexana/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/flexana-error.log
    CustomLog ${APACHE_LOG_DIR}/flexana-access.log combined
</VirtualHost>
```

### Nginx Configuration

For Nginx servers:

```nginx
server {
    listen 80;
    server_name admin-panel-flexana-egypt.com www.admin-panel-flexana-egypt.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name admin-panel-flexana-egypt.com www.admin-panel-flexana-egypt.com;
    
    # Point directly to Laravel public folder
    root /domains/admin-panel-flexana-egypt/flexana/public;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Then restart the web server:

```bash
# Apache
sudo systemctl restart apache2

# Nginx
sudo systemctl restart nginx
```

---

## Verification & Testing

### 1. Check Directory Structure

```bash
cd /domains/admin-panel-flexana-egypt/

# List directories
ls -la

# If using symlink, verify it
ls -la public_html
# Should show: public_html -> /path/to/flexana/public
```

### 2. Test Web Access

Visit these URLs:

1. **Homepage:** `https://admin-panel-flexana-egypt.com`
   - Should load your Laravel application

2. **Admin Panel:** `https://admin-panel-flexana-egypt.com/admin`
   - Should load Filament admin panel

3. **Health Check:** `https://admin-panel-flexana-egypt.com/up`
   - Should return "OK" or health status

### 3. Check for Common Issues

#### Assets Not Loading (404 errors)

**Check:**
```bash
# Verify assets exist
ls -la /domains/admin-panel-flexana-egypt/flexana/public/css/
ls -la /domains/admin-panel-flexana-egypt/flexana/public/js/
```

**Fix:**
```bash
cd /domains/admin-panel-flexana-egypt/flexana
npm run build
```

#### 500 Internal Server Error

**Check logs:**
```bash
tail -f /domains/admin-panel-flexana-egypt/flexana/storage/logs/laravel.log
```

**Common fixes:**
```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Fix permissions
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Re-optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### .env File Issues

**Verify .env exists:**
```bash
ls -la /domains/admin-panel-flexana-egypt/flexana/.env
```

**Check critical settings:**
```bash
cd /domains/admin-panel-flexana-egypt/flexana
php artisan config:show app
php artisan config:show database
```

---

## Security Considerations

### Files That Should NEVER Be Web-Accessible

These should be in `/domains/admin-panel-flexana-egypt/flexana/` (OUTSIDE public_html):

- `.env` - Contains secrets and passwords
- `vendor/` - PHP dependencies
- `storage/` - Logs, cache, uploaded files
- `database/` - Migrations and seeds
- `app/` - Application code
- `config/` - Configuration files
- `routes/` - Route definitions

### Only These Should Be Public

These are in `/domains/admin-panel-flexana-egypt/flexana/public/` (or public_html):

- `index.php` - Entry point
- `.htaccess` - URL rewriting
- `css/` - Compiled stylesheets
- `js/` - Compiled JavaScript
- `images/` - Public images
- `robots.txt` - SEO instructions
- `favicon.ico` - Site icon

### Test Security

Try accessing these URLs (should all return 404 or be blocked):

- `https://admin-panel-flexana-egypt.com/.env`
- `https://admin-panel-flexana-egypt.com/vendor/`
- `https://admin-panel-flexana-egypt.com/storage/`
- `https://admin-panel-flexana-egypt.com/config/`

If any of these are accessible, **your setup is insecure!**

---

## Quick Reference

### Recommended Setup (Symlink)

```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html  # backup first if needed
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
ls -la public_html  # verify symlink
```

### Alternative Setup (Move Public)

```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html  # backup first if needed
cp -r flexana/public public_html
# Edit public_html/index.php to update paths
```

### Verify Deployment

```bash
# Check structure
ls -la /domains/admin-panel-flexana-egypt/

# Test Laravel
cd /domains/admin-panel-flexana-egypt/flexana
php artisan about

# Check logs
tail -f storage/logs/laravel.log
```

---

## Need Help?

1. Check Laravel documentation: https://laravel.com/docs/deployment
2. Check your server's error logs
3. Check Laravel logs: `storage/logs/laravel.log`
4. Contact your hosting provider for symlink support

---

**Last Updated:** February 2026
