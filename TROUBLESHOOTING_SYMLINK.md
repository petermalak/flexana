# Troubleshooting: Can't Reach Project After Creating Symlink

## Problem

After creating the symlink `public_html -> flexana/public`, the website is not accessible or shows errors.

---

## Quick Diagnosis (30 seconds)

Run these commands to identify the issue:

```bash
# 1. Check if symlink exists and is correct
ls -la /domains/admin-panel-flexana-egypt/public_html

# 2. Check if target exists
ls -la /domains/admin-panel-flexana-egypt/flexana/public/index.php

# 3. Test symlink
readlink /domains/admin-panel-flexana-egypt/public_html

# 4. Check Laravel status
cd /domains/admin-panel-flexana-egypt/flexana && php artisan about
```

---

## Common Issues & Solutions

### Issue 1: Symlink Not Created (Most Common)

**Symptoms:**
- 404 error
- "Directory not found"
- Browser shows empty page

**Check:**
```bash
cd /domains/admin-panel-flexana-egypt/
ls -la public_html
```

**If it shows a regular directory instead of a symlink (arrow `->`):**

**Solution:**
```bash
cd /domains/admin-panel-flexana-egypt/

# Backup existing public_html
mv public_html public_html_backup_$(date +%Y%m%d_%H%M%S)

# Create symlink with ABSOLUTE path
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Verify - should show arrow (->)
ls -la public_html
```

---

### Issue 2: Hosting Doesn't Support Symlinks

**Symptoms:**
- "Forbidden" error
- 403 error
- Symlink appears but site doesn't work

**Check:**
```bash
# Test if symlinks are allowed
cd /tmp
echo "test" > test_file.txt
ln -s test_file.txt test_link.txt
cat test_link.txt

# If this fails, symlinks are disabled
rm test_file.txt test_link.txt 2>/dev/null
```

**Solution: Use the "Move Public Contents" method instead**

```bash
cd /domains/admin-panel-flexana-egypt/

# Remove symlink
rm -rf public_html

# Copy public folder
cp -r flexana/public public_html

# Edit index.php
nano public_html/index.php
```

**Update these 3 lines in `index.php`:**

```php
// Change FROM:
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')

// Change TO:
if (file_exists($maintenance = __DIR__.'/../flexana/storage/framework/maintenance.php')) {
require __DIR__.'/../flexana/vendor/autoload.php';
(require_once __DIR__.'/../flexana/bootstrap/app.php')
```

**Complete working `index.php`:**

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

---

### Issue 3: Web Server Can't Follow Symlinks

**Symptoms:**
- 403 Forbidden
- "You don't have permission to access this resource"

**Solution 1: Enable FollowSymLinks in .htaccess**

```bash
cd /domains/admin-panel-flexana-egypt/flexana/public/

# Edit .htaccess
nano .htaccess
```

Add at the top:
```apache
Options +FollowSymLinks
```

Complete `.htaccess`:
```apache
Options +FollowSymLinks

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewritCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**Solution 2: Contact hosting provider**

Ask them to enable `FollowSymLinks` for your account or use the "Move Public Contents" method.

---

### Issue 4: Wrong Symlink Path (Relative vs Absolute)

**Symptoms:**
- Symlink shows but broken
- "Too many redirects"

**Check:**
```bash
readlink /domains/admin-panel-flexana-egypt/public_html
```

**If it shows a relative path like `../flexana/public`, recreate with absolute path:**

```bash
cd /domains/admin-panel-flexana-egypt/

# Remove broken symlink
rm public_html

# Create with ABSOLUTE path (recommended)
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Verify
readlink public_html
# Should show: /domains/admin-panel-flexana-egypt/flexana/public
```

---

### Issue 5: File Permissions

**Symptoms:**
- 403 Forbidden
- "Permission denied"

**Solution:**

```bash
# Fix permissions on Laravel app
cd /domains/admin-panel-flexana-egypt/flexana

# Set correct permissions
chmod 755 public
chmod 644 public/index.php
chmod 644 public/.htaccess
chmod -R 775 storage bootstrap/cache

# Fix ownership (if you have sudo access)
# Replace 'your_user' with your actual username
chown -R your_user:your_user /domains/admin-panel-flexana-egypt/flexana
```

---

### Issue 6: Laravel Not Configured

**Symptoms:**
- Blank page
- 500 Internal Server Error
- "Application key not set"

**Solution:**

```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Check if .env exists
ls -la .env

# If missing, create it
cp .env.example .env

# Generate key
php artisan key:generate --force

# Set production values in .env
nano .env
```

Essential `.env` values:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin-panel-flexana-egypt.com
```

Then optimize:
```bash
php artisan optimize
```

---

### Issue 7: Assets Built But Not Accessible

**Symptoms:**
- Page loads but no CSS/JS
- 404 errors for CSS/JS files

**Check:**
```bash
# Verify assets exist
ls -la /domains/admin-panel-flexana-egypt/flexana/public/css/
ls -la /domains/admin-panel-flexana-egypt/flexana/public/js/

# Check if accessible through symlink
ls -la /domains/admin-panel-flexana-egypt/public_html/css/
ls -la /domains/admin-panel-flexana-egypt/public_html/js/
```

**Solution:**
```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Rebuild assets
npm run build

# Verify files created
ls -la public/css/
ls -la public/js/

# If using "Move Public Contents" method, copy assets
cp -r public/css /domains/admin-panel-flexana-egypt/public_html/
cp -r public/js /domains/admin-panel-flexana-egypt/public_html/
```

---

### Issue 8: Storage Link Missing

**Symptoms:**
- Images not loading
- "Storage link not found"

**Solution:**
```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Remove old storage link if exists
rm public/storage 2>/dev/null

# Create storage link
php artisan storage:link

# Verify
ls -la public/storage
# Should show: storage -> ../storage/app/public
```

---

## Complete Reset Procedure

If nothing works, try this complete reset:

```bash
cd /domains/admin-panel-flexana-egypt/

# Step 1: Remove public_html
rm -rf public_html

# Step 2: Verify Laravel files are intact
ls -la flexana/public/index.php
cat flexana/.env | grep APP_KEY

# Step 3: Clear Laravel caches
cd flexana
php artisan optimize:clear
php artisan optimize

# Step 4: Try symlink again with absolute path
cd /domains/admin-panel-flexana-egypt/
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Step 5: Verify
ls -la public_html
readlink public_html

# Step 6: Test with curl
curl -I https://admin-panel-flexana-egypt.com
```

---

## Testing After Fix

### 1. Test Homepage
```bash
curl -I https://admin-panel-flexana-egypt.com
# Should return: HTTP/1.1 200 OK
```

### 2. Test Admin Panel
```bash
curl -I https://admin-panel-flexana-egypt.com/admin
# Should return: HTTP/1.1 200 OK or 302 (redirect to login)
```

### 3. Test Health Endpoint
```bash
curl https://admin-panel-flexana-egypt.com/up
# Should return: OK or health status
```

### 4. Check Logs
```bash
tail -f /domains/admin-panel-flexana-egypt/flexana/storage/logs/laravel.log
```

### 5. Browser Test
Open browser and visit:
- https://admin-panel-flexana-egypt.com (homepage)
- https://admin-panel-flexana-egypt.com/admin (admin panel)

Check browser console (F12) for any JavaScript errors.

---

## Still Not Working?

### Check Web Server Error Logs

```bash
# Apache error logs (if accessible)
tail -n 100 /var/log/apache2/error.log

# cPanel error logs
# Go to cPanel → Error Log (in Metrics section)
```

### Enable Debug Mode Temporarily (NOT for production!)

```bash
cd /domains/admin-panel-flexana-egypt/flexana
nano .env
```

Change:
```env
APP_DEBUG=true  # TEMPORARILY for debugging
```

Visit the site to see detailed error messages.

**IMPORTANT: Set back to `false` after fixing:**
```env
APP_DEBUG=false
```

### Contact Hosting Support

If symlinks still don't work after all troubleshooting:

1. **Ask hosting provider:** "Does my hosting plan support symbolic links (symlinks)?"
2. **Alternative:** Use the "Move Public Contents" method (no symlinks needed)
3. **Consider:** Upgrading to VPS/dedicated hosting if symlinks are essential

---

## Recommended Solution Flowchart

```
Can't Access Site After Symlink
        ↓
    Check if symlink exists
        ↓
   [YES]              [NO]
    ↓                  ↓
Test symlink      Create symlink
support           with absolute path
    ↓                  ↓
[WORKS]  [DOESN'T]     Test site
    ↓        ↓             ↓
Configure  Use      [WORKS] [DOESN'T]
.htaccess  "Move      ↓        ↓
           Public"  DONE  Check permissions
           Method            & Laravel config
             ↓                    ↓
           Edit              Fix & test
           index.php             ↓
             ↓                 DONE
           DONE
```

---

## Prevention for Future Deployments

Always use absolute paths when creating symlinks:
```bash
# ✅ GOOD (absolute path)
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# ❌ BAD (relative path - may break)
ln -s ../flexana/public public_html
```

Always verify symlink after creation:
```bash
ls -la public_html
readlink public_html
ls -la public_html/index.php
```

---

## Summary

**Most Common Fix (90% of cases):**
1. Remove public_html: `rm -rf public_html`
2. Create symlink with absolute path: `ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html`
3. Verify: `ls -la public_html` (should show `->`)

**If symlinks not supported (10% of cases):**
1. Copy public folder: `cp -r flexana/public public_html`
2. Edit `public_html/index.php` to add `flexana/` to all paths
3. Test: Visit site in browser

---

**Need more help? Check:**
- [DOMAIN_SETUP_GUIDE.md](./DOMAIN_SETUP_GUIDE.md)
- [PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)
- [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)
