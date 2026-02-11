# Deployment Commands for Your Server

**Your Server Path:** `/home/flexanastudios/domains/admin-panel-flexana-egypt`

---

## Quick Deployment (Copy & Paste)

### Option 1: Symlink Method (Recommended - Try This First)

```bash
# Navigate to your directory
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Step 1: Install dependencies (if not done yet)
cd flexana
composer install --optimize-autoloader --no-dev --no-interaction
npm ci
npm run build

# Step 2: Configure Laravel
cp .env.example .env
nano .env  # Edit with your database credentials and set APP_DEBUG=false
php artisan key:generate --force
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan optimize

# Step 3: Create symlink
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
rm -rf public_html
ln -s /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public public_html

# Step 4: Verify symlink
ls -la public_html
# Should show: public_html -> /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public

# Step 5: Test
readlink public_html
ls -la public_html/index.php
```

---

### Option 2: Move Public Contents (If Symlinks Don't Work)

```bash
# Navigate to your directory
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Step 1: Copy public folder
rm -rf public_html
cp -r flexana/public public_html

# Step 2: Edit index.php
nano public_html/index.php
```

**In the editor, change these 3 lines:**

Find:
```php
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
```
Change to:
```php
if (file_exists($maintenance = __DIR__.'/../flexana/storage/framework/maintenance.php')) {
```

Find:
```php
require __DIR__.'/../vendor/autoload.php';
```
Change to:
```php
require __DIR__.'/../flexana/vendor/autoload.php';
```

Find:
```php
(require_once __DIR__.'/../bootstrap/app.php')
```
Change to:
```php
(require_once __DIR__.'/../flexana/bootstrap/app.php')
```

Save and exit (Ctrl+X, then Y, then Enter)

---

## Complete Working index.php for Option 2

If you want to copy the entire file, here's the complete working `index.php`:

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

**To use this, run:**
```bash
cat > /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html/index.php << 'INDEXPHP'
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
INDEXPHP
```

---

## Diagnostic Commands

Run these to check your setup:

```bash
# Check your current directory
pwd
# Should show: /home/flexanastudios/domains/admin-panel-flexana-egypt

# Check if flexana directory exists
ls -la flexana/

# Check if public_html exists and what type it is
ls -la public_html

# If it's a symlink, check where it points
readlink public_html

# Check if Laravel files are accessible
ls -la flexana/public/index.php
ls -la flexana/.env
ls -la flexana/vendor/

# Check permissions
ls -ld flexana/storage
ls -ld flexana/bootstrap/cache

# Test Laravel
cd flexana
php artisan about
```

---

## Post-Deployment Tasks

```bash
# Create admin user
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
php artisan make:filament-user

# Generate API keys (if needed)
php artisan api:generate-key "Production App"

# Check logs for errors
tail -f storage/logs/laravel.log
```

---

## Testing

```bash
# Test with curl
curl -I https://admin-panel-flexana-egypt.com

# Should return: HTTP/1.1 200 OK or HTTP/2 200

# Check if site is accessible
curl https://admin-panel-flexana-egypt.com
```

---

## If You're Getting Errors

### Error: "Can't reach the project"

**Diagnose:**
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Check symlink
ls -la public_html
readlink public_html

# Check target exists
ls -la /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public/

# Check index.php exists
ls -la public_html/index.php
```

**Fix (if symlink isn't working):**
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Remove symlink
rm -rf public_html

# Use copy method instead
cp -r flexana/public public_html

# Edit index.php
nano public_html/index.php
# Add 'flexana/' to all 3 paths as shown above
```

### Error: "500 Internal Server Error"

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

# Fix permissions
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan optimize:clear

# Re-optimize
php artisan optimize

# Check logs
tail -f storage/logs/laravel.log
```

### Error: "Assets not loading (CSS/JS)"

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

# Rebuild assets
npm run build

# If using copy method (not symlink), copy assets
cp -r public/css /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html/
cp -r public/js /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html/
```

---

## Environment Variables for .env

```env
APP_NAME="Flexana"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin-panel-flexana-egypt.com
APP_TIMEZONE=UTC

# Database (update with your actual credentials)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexana_production
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

# Cache & Session
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=error
```

---

## Quick Commands Reference

```bash
# Navigate to app
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana

# Clear all caches
php artisan optimize:clear

# Optimize for production
php artisan optimize

# Check application
php artisan about

# View routes
php artisan route:list

# View logs
tail -f storage/logs/laravel.log

# Create admin
php artisan make:filament-user
```

---

## Automated Diagnostic

Run this diagnostic script:

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
chmod +x diagnose-deployment.sh

# Edit the script to use your paths
sed -i 's|/domains/admin-panel-flexana-egypt|/home/flexanastudios/domains/admin-panel-flexana-egypt|g' diagnose-deployment.sh

# Run it
./diagnose-deployment.sh
```

---

## Complete Reset (If Nothing Works)

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Step 1: Remove public_html
rm -rf public_html

# Step 2: Clear Laravel caches
cd flexana
php artisan optimize:clear
php artisan cache:clear
php artisan config:clear

# Step 3: Fix permissions
chmod -R 775 storage bootstrap/cache

# Step 4: Re-optimize
php artisan optimize

# Step 5: Try symlink again
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
ln -s /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public public_html

# Step 6: Verify
ls -la public_html
readlink public_html

# Step 7: Test
curl -I https://admin-panel-flexana-egypt.com
```

---

## Directory Structure Verification

Your setup should look like this:

```
/home/flexanastudios/domains/admin-panel-flexana-egypt/
├── flexana/                                    ← Your Laravel app
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── public/
│   │   ├── index.php                          ← Laravel entry point
│   │   ├── .htaccess
│   │   ├── css/
│   │   └── js/
│   ├── storage/
│   ├── vendor/
│   └── .env
└── public_html/                                ← Symlink to flexana/public
    → /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public
```

**Verify with:**
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
tree -L 2 -a
# or
ls -la
ls -la flexana/
ls -la public_html
```

---

## Need Help?

1. Run diagnostic: `./diagnose-deployment.sh`
2. Check Laravel logs: `tail -f flexana/storage/logs/laravel.log`
3. Check web server error logs in cPanel
4. See: [TROUBLESHOOTING_SYMLINK.md](./TROUBLESHOOTING_SYMLINK.md)

---

**Your Specific Paths:**
- Base: `/home/flexanastudios/domains/admin-panel-flexana-egypt`
- Laravel App: `/home/flexanastudios/domains/admin-panel-flexana-egypt/flexana`
- Public Folder: `/home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public`
- Web Root: `/home/flexanastudios/domains/admin-panel-flexana-egypt/public_html`
