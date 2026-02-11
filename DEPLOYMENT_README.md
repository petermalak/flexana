# Flexana Production Deployment Documentation

Complete guide for deploying the Flexana Laravel application to production.

---

## 📚 Documentation Overview

This deployment package includes several guides to help you deploy successfully:

### Core Deployment Guides

1. **[PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)**
   - Complete deployment guide for cPanel/shared hosting
   - Step-by-step instructions
   - Two deployment methods (symlink vs. move public)
   - Troubleshooting section

2. **[DOMAIN_SETUP_GUIDE.md](./DOMAIN_SETUP_GUIDE.md)**
   - Detailed explanation of domain configuration
   - How to make domain point to Laravel public folder
   - Security considerations
   - Multiple setup methods explained

3. **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)**
   - Printable checklist for deployment
   - Pre-deployment preparation
   - Step-by-step tasks
   - Post-deployment verification
   - Security checklist

4. **[DEPLOYMENT.md](./DEPLOYMENT.md)**
   - General production deployment guide
   - Server requirements
   - Environment configuration
   - Web server configuration (Apache/Nginx)

### Deployment Scripts

5. **[deploy-production.sh](./deploy-production.sh)**
   - Automated deployment script for production
   - Interactive prompts for method selection
   - Handles both symlink and move public methods

6. **[deploy.sh](./deploy.sh)**
   - General deployment script
   - Works for staging and production
   - Includes optimization and caching

---

## 🚀 Quick Start

### Your Deployment Path

```
Server path: /domains/admin-panel-flexana-egypt/public_html/
Domain: https://admin-panel-flexana-egypt.com
```

### Fastest Deployment (3 Steps)

1. **Upload & Install**
   ```bash
   # Upload files to /domains/admin-panel-flexana-egypt/flexana/
   cd /domains/admin-panel-flexana-egypt/flexana
   composer install --no-dev
   npm ci && npm run build
   ```

2. **Configure**
   ```bash
   cp .env.example .env
   nano .env  # Edit with production values
   php artisan key:generate --force
   php artisan migrate --force
   ```

3. **Link Public Folder**
   ```bash
   cd /domains/admin-panel-flexana-egypt/
   rm -rf public_html
   ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
   ```

**Done!** Visit https://admin-panel-flexana-egypt.com

---

## 📖 Recommended Reading Order

### First Time Deploying Laravel?
1. Read **[DOMAIN_SETUP_GUIDE.md](./DOMAIN_SETUP_GUIDE.md)** - Understand the directory structure
2. Review **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)** - Know what to prepare
3. Follow **[PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)** - Complete deployment

### Experienced with Laravel?
1. Review **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)** - Quick reference
2. Use **[deploy-production.sh](./deploy-production.sh)** script - Automated deployment
3. Reference **[PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)** - If issues arise

---

## 🎯 Choose Your Deployment Method

### Method 1: Symlink (Recommended)

**Best for:** Modern hosting, VPS, dedicated servers

**Directory Structure:**
```
/domains/admin-panel-flexana-egypt/
├── flexana/              # Laravel app (secure)
│   └── public/           # Public files
└── public_html/          # Symlink → flexana/public
```

**Advantages:**
- ✅ Most secure
- ✅ Easy updates
- ✅ No file duplication
- ✅ Clean structure

**Setup:**
```bash
cd /domains/admin-panel-flexana-egypt/
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
```

**Guide:** [PRODUCTION_DEPLOYMENT_CPANEL.md - Option 1](./PRODUCTION_DEPLOYMENT_CPANEL.md#option-1-symlink-recommended)

---

### Method 2: Move Public Contents

**Best for:** Shared hosting without symlink support

**Directory Structure:**
```
/domains/admin-panel-flexana-egypt/
├── flexana/              # Laravel app (secure)
└── public_html/          # Copy of public/ with modified index.php
```

**Advantages:**
- ✅ Works everywhere
- ✅ No special server requirements

**Disadvantages:**
- ❌ Must copy assets after builds
- ❌ More maintenance

**Setup:**
```bash
cd /domains/admin-panel-flexana-egypt/
cp -r flexana/public public_html
# Edit public_html/index.php paths
```

**Guide:** [PRODUCTION_DEPLOYMENT_CPANEL.md - Option 2](./PRODUCTION_DEPLOYMENT_CPANEL.md#option-2-move-public-contents-alternative)

---

## 🔧 Server Requirements

### Minimum Requirements
- **PHP:** 8.2 or higher
- **Composer:** Latest version
- **Node.js:** 18.x or higher
- **NPM:** 9.x or higher
- **Database:** MySQL 8.0+ or MariaDB 10.3+
- **Web Server:** Apache 2.4+ or Nginx 1.18+

### Required PHP Extensions
- BCMath, Ctype, cURL, DOM, Fileinfo, JSON
- Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML

### Check Your Server
```bash
# Check PHP version
php -v

# Check PHP extensions
php -m

# Check Composer
composer --version

# Check Node.js
node -v

# Check NPM
npm -v
```

---

## 📋 Deployment Checklist

Print or reference **[DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md)** during deployment.

### Critical Pre-Deployment Items
- [ ] Server meets requirements
- [ ] Database created
- [ ] SSL certificate obtained
- [ ] Backup strategy in place
- [ ] All tests passing locally

### Critical Deployment Items
- [ ] Dependencies installed
- [ ] `.env` configured with production values
- [ ] `APP_DEBUG=false` ⚠️ CRITICAL
- [ ] Database migrations run
- [ ] Permissions set correctly
- [ ] Public folder linked/configured

### Critical Post-Deployment Items
- [ ] Admin user created
- [ ] Site accessible via HTTPS
- [ ] No security vulnerabilities (`.env`, `vendor/` not accessible)
- [ ] Logs show no errors
- [ ] Cron jobs configured

---

## 🔒 Security Checklist

### Application Security
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] Strong passwords and keys
- [ ] CSRF protection enabled
- [ ] API authentication configured

### Server Security
- [ ] `.env` file NOT web-accessible
- [ ] `vendor/` directory NOT web-accessible
- [ ] `storage/` directory NOT web-accessible
- [ ] SSL certificate installed
- [ ] HTTPS enforced

### Test Security
Visit these URLs (should all return 404):
- `https://your-domain.com/.env`
- `https://your-domain.com/vendor/`
- `https://your-domain.com/storage/`

---

## 🛠️ Common Issues & Solutions

### 500 Internal Server Error
```bash
# Fix permissions
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan cache:clear

# Check logs
tail -f storage/logs/laravel.log
```

### Assets Not Loading (CSS/JS 404)
```bash
# Rebuild assets
npm run build

# Verify build folder exists
ls -la public/css public/js
```

### Database Connection Error
- Check `.env` database credentials
- Verify database exists
- Test connection: `php artisan tinker` → `DB::connection()->getPdo();`

### Symlink Not Working / Can't Reach Project
- **See [TROUBLESHOOTING_SYMLINK.md](./TROUBLESHOOTING_SYMLINK.md)** for complete diagnosis
- Check if hosting supports symlinks
- Use "Move Public Contents" method instead
- See [DOMAIN_SETUP_GUIDE.md](./DOMAIN_SETUP_GUIDE.md)

---

## 📞 Getting Help

### Documentation Resources
- Laravel Docs: https://laravel.com/docs
- Filament Docs: https://filamentphp.com/docs
- Laravel Deployment: https://laravel.com/docs/deployment

### Check Logs
```bash
# Laravel application logs
tail -f storage/logs/laravel.log

# Web server logs (Apache)
tail -f /var/log/apache2/error.log

# Web server logs (Nginx)
tail -f /var/log/nginx/error.log
```

### Debug Mode (ONLY for troubleshooting, never in production)
```env
# In .env - ONLY temporarily for debugging
APP_DEBUG=true

# Remember to set back to false after fixing!
APP_DEBUG=false
```

---

## 🔄 Updating the Application

```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Pull latest code
git pull origin main

# Update dependencies
composer install --no-dev
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan optimize:clear
php artisan optimize

# If using "move public contents" method, copy assets
# cp -r public/css ../public_html/
# cp -r public/js ../public_html/
```

---

## 📁 Project Structure

```
flexana/
├── app/                    # Application code
│   ├── Domain/             # Domain logic
│   ├── Filament/           # Admin panel
│   ├── Http/               # Controllers
│   ├── Models/             # Eloquent models
│   └── ...
├── bootstrap/              # Bootstrap files
├── config/                 # Configuration files
├── database/               # Migrations & seeders
├── public/                 # Web-accessible files (YOUR WEB ROOT)
│   ├── index.php           # Entry point
│   ├── .htaccess           # URL rewriting
│   ├── css/                # Compiled CSS
│   └── js/                 # Compiled JS
├── resources/              # Raw assets & views
├── routes/                 # Route definitions
├── storage/                # Logs, cache, uploads
├── vendor/                 # PHP dependencies
├── .env                    # Environment config (NEVER PUBLIC)
├── artisan                 # Artisan CLI
├── composer.json           # PHP dependencies
└── package.json            # Node dependencies
```

---

## 🎉 Post-Deployment Tasks

### 1. Create Admin User
```bash
php artisan make:filament-user
```

### 2. Generate API Keys
```bash
php artisan api:generate-key "Production App"
```
Save the keys securely!

### 3. Set Up Cron Job
Add to crontab:
```cron
* * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
```

### 4. Test Everything
- [ ] Homepage: https://admin-panel-flexana-egypt.com
- [ ] Admin: https://admin-panel-flexana-egypt.com/admin
- [ ] Health: https://admin-panel-flexana-egypt.com/up
- [ ] API endpoints with Postman

### 5. Configure Monitoring
- Set up uptime monitoring
- Configure error tracking (optional: Sentry)
- Set up automated backups

---

## 📊 Performance Optimization

### Enable Caching
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Use Redis (if available)
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Optimize Composer Autoloader
```bash
composer install --optimize-autoloader --no-dev
```

### Enable OPcache
Check with hosting provider if not already enabled.

---

## 🔐 Environment Variables Reference

### Essential Settings
```env
APP_NAME="Flexana"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin-panel-flexana-egypt.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database
DB_USERNAME=your_username
DB_PASSWORD=your_password

CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

LOG_CHANNEL=daily
LOG_LEVEL=error
```

See [ENV_CONFIGURATION.md](./ENV_CONFIGURATION.md) for complete list.

---

## 🗂️ Additional Documentation

- **[AMELIA_INTEGRATION_SETUP.md](./AMELIA_INTEGRATION_SETUP.md)** - Amelia integration
- **[API_AUTHENTICATION_GUIDE.md](./API_AUTHENTICATION_GUIDE.md)** - API authentication
- **[API_V1_ROUTES.md](./API_V1_ROUTES.md)** - API routes documentation
- **[FIRESTORE_MIGRATION.md](./FIRESTORE_MIGRATION.md)** - Firebase/Firestore setup
- **[MOBILE_API_README.md](./MOBILE_API_README.md)** - Mobile API documentation
- **[POSTMAN_COLLECTION_README.md](./POSTMAN_COLLECTION_README.md)** - API testing
- **[TROUBLESHOOTING_500_ERROR.md](./TROUBLESHOOTING_500_ERROR.md)** - 500 error fixes

---

## ✅ Quick Verification

After deployment, verify these:

```bash
# Check application status
php artisan about

# List routes
php artisan route:list

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# View recent logs
tail -n 50 storage/logs/laravel.log
```

---

## 📝 Notes

- Always backup before deployment
- Test in staging environment first (if available)
- Keep deployment documentation updated
- Document any custom configurations
- Save all credentials securely

---

## 🎯 Summary

**For your specific deployment:**

1. **Upload to:** `/domains/admin-panel-flexana-egypt/flexana/`
2. **Public folder:** `/domains/admin-panel-flexana-egypt/flexana/public/`
3. **Web root:** `/domains/admin-panel-flexana-egypt/public_html/`
4. **Domain:** `https://admin-panel-flexana-egypt.com`

**Recommended method:** Symlink `public_html` → `flexana/public`

**Alternative method:** Copy `flexana/public` to `public_html` and modify `index.php`

**Complete guide:** [PRODUCTION_DEPLOYMENT_CPANEL.md](./PRODUCTION_DEPLOYMENT_CPANEL.md)

---

**Good luck with your deployment! 🚀**

If you encounter any issues, refer to the specific guide or check the troubleshooting sections.

---

**Last Updated:** February 2026
