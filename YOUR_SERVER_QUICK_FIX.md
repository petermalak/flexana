# Quick Fix for Your Server

**Server:** flexanastudios@server35  
**Path:** `/home/flexanastudios/domains/admin-panel-flexana-egypt`

---

## 🚨 Can't Reach Project After Symlink? Try This:

### Method 1: Fix Symlink (30 seconds)

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
rm -rf public_html
ln -s /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public public_html
ls -la public_html
```

**Expected output:**
```
public_html -> /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public
```

**Test immediately:**
```bash
curl -I https://admin-panel-flexana-egypt.com
```

If this returns `HTTP/1.1 200 OK` or `HTTP/2 200`, you're done! ✅

---

### Method 2: If Symlink Doesn't Work (2 minutes)

Your hosting might not support symlinks. Use this instead:

```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt

# Copy public folder
rm -rf public_html
cp -r flexana/public public_html

# Fix index.php with one command
sed -i "s|__DIR__.'/../storage|__DIR__.'/../flexana/storage|g" public_html/index.php
sed -i "s|__DIR__.'/../vendor|__DIR__.'/../flexana/vendor|g" public_html/index.php
sed -i "s|__DIR__.'/../bootstrap|__DIR__.'/../flexana/bootstrap|g" public_html/index.php

# Verify changes
grep "flexana" public_html/index.php
```

**Test:**
```bash
curl -I https://admin-panel-flexana-egypt.com
```

---

## 🔍 Quick Diagnosis

Run these to see what's wrong:

```bash
# Check current setup
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
pwd
ls -la public_html
readlink public_html

# Check Laravel app
ls -la flexana/public/index.php
cat flexana/.env | grep APP_KEY

# Test Laravel
cd flexana
php artisan about
```

---

## ✅ Verification Checklist

After deployment, verify:

```bash
# 1. Symlink or public_html exists
ls -la /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html

# 2. index.php accessible
ls -la /home/flexanastudios/domains/admin-panel-flexana-egypt/public_html/index.php

# 3. .env configured
cat /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/.env | grep -E "APP_KEY|APP_DEBUG|APP_ENV"

# 4. Permissions correct
ls -ld /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/storage

# 5. Site responds
curl -I https://admin-panel-flexana-egypt.com
```

---

## 🛠️ Common Fixes

### Fix Permissions
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
chmod -R 775 storage bootstrap/cache
```

### Clear & Rebuild Caches
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
php artisan optimize:clear
php artisan optimize
```

### Rebuild Assets
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
npm run build

# If using copy method (not symlink)
cp -r public/css ../public_html/
cp -r public/js ../public_html/
```

### Check Logs
```bash
tail -f /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/storage/logs/laravel.log
```

---

## 📝 Post-Deployment

### Create Admin User
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
php artisan make:filament-user
```

### Set Up Cron Job
Add to crontab:
```
* * * * * cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🆘 Still Not Working?

### Test Symlink Support
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt
echo "test" > test.txt
ln -s test.txt testlink.txt
cat testlink.txt
# If this works, symlinks are supported
rm test.txt testlink.txt
```

### Check .htaccess
```bash
cat /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana/public/.htaccess
```

Should contain:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    # ... rest of file
</IfModule>
```

### Enable Debug Temporarily (ONLY for troubleshooting)
```bash
cd /home/flexanastudios/domains/admin-panel-flexana-egypt/flexana
nano .env
# Change: APP_DEBUG=true

# Visit site to see error
# Then change back: APP_DEBUG=false
```

---

## 📞 Full Documentation

- **[DEPLOYMENT_COMMANDS_FOR_YOUR_SERVER.md](./DEPLOYMENT_COMMANDS_FOR_YOUR_SERVER.md)** - Complete commands for your server
- **[TROUBLESHOOTING_SYMLINK.md](./TROUBLESHOOTING_SYMLINK.md)** - Detailed troubleshooting
- **[PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)** - Full deployment guide

---

**Remember:** Your base path is `/home/flexanastudios/domains/admin-panel-flexana-egypt`

All commands above are already adjusted for your server!
