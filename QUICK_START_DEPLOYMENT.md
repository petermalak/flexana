# Quick Start: Deploy to Production

**Target:** `/domains/admin-panel-flexana-egypt/public_html/`

**Domain:** `https://admin-panel-flexana-egypt.com`

---

## 🚀 3-Step Deployment (5 Minutes)

### Step 1: Upload & Install (2 min)

```bash
# Connect via SSH
ssh your_username@your_server

# Navigate and upload
cd /domains/admin-panel-flexana-egypt/
# Upload your files to: flexana/

# Install dependencies
cd flexana
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

### Step 2: Configure Environment (2 min)

```bash
# Create .env file
cp .env.example .env
nano .env
```

**Update these values:**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin-panel-flexana-egypt.com

DB_DATABASE=your_database_name
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password
```

**Save and run:**
```bash
php artisan key:generate --force
php artisan migrate --force
chmod -R 775 storage bootstrap/cache
php artisan storage:link
php artisan optimize
```

### Step 3: Link Public Folder (1 min)

```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
```

**✅ Done! Visit:** `https://admin-panel-flexana-egypt.com`

---

## 🎯 Alternative: Use Deployment Script

```bash
cd /domains/admin-panel-flexana-egypt/flexana
chmod +x deploy-production.sh
./deploy-production.sh
```

The script will guide you through the entire process!

---

## 🔧 Post-Deployment (2 min)

### Create Admin User
```bash
cd /domains/admin-panel-flexana-egypt/flexana
php artisan make:filament-user
```

### Set Up Cron Job
Add via cPanel or crontab:
```
* * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
```

### Test Your Site
- ✅ Homepage: https://admin-panel-flexana-egypt.com
- ✅ Admin: https://admin-panel-flexana-egypt.com/admin
- ✅ Health: https://admin-panel-flexana-egypt.com/up

---

## 🆘 If Symlinks Don't Work

If you get an error creating the symlink:

```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html
cp -r flexana/public public_html
```

**Then edit:** `public_html/index.php`

Change these 3 lines:
```php
// FROM:
__DIR__.'/../storage/framework/maintenance.php'
__DIR__.'/../vendor/autoload.php'
__DIR__.'/../bootstrap/app.php'

// TO:
__DIR__.'/../flexana/storage/framework/maintenance.php'
__DIR__.'/../flexana/vendor/autoload.php'
__DIR__.'/../flexana/bootstrap/app.php'
```

---

## 📚 Need More Details?

- **[DEPLOYMENT_README.md](./DEPLOYMENT_README.md)** - Complete overview
- **[PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)** - Full guide
- **[DOMAIN_SETUP_GUIDE.md](./DOMAIN_SETUP_GUIDE.md)** - Domain configuration explained
- **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)** - Printable checklist

---

## 🐛 Troubleshooting

### 500 Error
```bash
chmod -R 775 storage bootstrap/cache
php artisan config:clear
php artisan cache:clear
tail -f storage/logs/laravel.log
```

### CSS/JS Not Loading
```bash
npm run build
ls -la public/css public/js  # verify files exist
```

### Database Error
```bash
nano .env  # verify DB credentials
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## ✅ Security Check

These should return 404:
- https://admin-panel-flexana-egypt.com/.env
- https://admin-panel-flexana-egypt.com/vendor/
- https://admin-panel-flexana-egypt.com/storage/

If accessible, your setup is insecure!

---

**That's it! Your Laravel app should now be live! 🎉**
