# Production Deployment Checklist

Quick reference checklist for deploying Flexana to production.

---

## Pre-Deployment

### Local Preparation
- [ ] All code changes committed to Git
- [ ] All tests passing locally
- [ ] Database migrations tested
- [ ] Assets built successfully (`npm run build`)
- [ ] No sensitive data in code (API keys, passwords)
- [ ] `.env.example` updated with new variables (if any)
- [ ] Documentation updated
- [ ] Postman collection tested

### Server Preparation
- [ ] Server meets requirements (PHP 8.2+, MySQL 8.0+, Composer, Node.js 18+)
- [ ] Database created
- [ ] Database user created with proper permissions
- [ ] SSL certificate obtained (Let's Encrypt recommended)
- [ ] Backup strategy in place
- [ ] Access to server (SSH/SFTP)

---

## Deployment Process

### Step 1: Upload Application
- [ ] Connect to server via SSH/SFTP
- [ ] Navigate to `/domains/admin-panel-flexana-egypt/`
- [ ] Upload/clone repository to `flexana/` directory
- [ ] Verify all files uploaded correctly

### Step 2: Install Dependencies
```bash
cd /domains/admin-panel-flexana-egypt/flexana
```

- [ ] Run: `composer install --optimize-autoloader --no-dev --no-interaction`
- [ ] Run: `npm ci`
- [ ] Run: `npm run build`
- [ ] Verify `public/css` and `public/js` directories exist with content

### Step 3: Environment Configuration
- [ ] Copy `.env.example` to `.env`
- [ ] Edit `.env` file with production values:

#### Essential Settings
- [ ] `APP_NAME="Flexana"`
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false` ⚠️ CRITICAL
- [ ] `APP_URL=https://admin-panel-flexana-egypt.com`
- [ ] Database credentials configured
- [ ] Mail server configured
- [ ] Cache/session drivers set
- [ ] Logging configured (`LOG_LEVEL=error`)

#### Security Settings
- [ ] Generate app key: `php artisan key:generate --force`
- [ ] Strong database password set
- [ ] API keys noted for documentation

### Step 4: Database Setup
- [ ] Run: `php artisan migrate --force`
- [ ] Verify migrations completed successfully
- [ ] Run seeders if needed: `php artisan db:seed`

### Step 5: Set Permissions
```bash
chmod -R 775 storage bootstrap/cache
```

- [ ] Storage directory writable (775)
- [ ] Bootstrap/cache directory writable (775)
- [ ] Ownership set correctly (if using www-data user)

### Step 6: Storage & Optimization
- [ ] Run: `php artisan storage:link`
- [ ] Run: `php artisan config:cache`
- [ ] Run: `php artisan route:cache`
- [ ] Run: `php artisan view:cache`
- [ ] Run: `php artisan event:cache`

### Step 7: Configure Web Root
Choose ONE method:

#### Option A: Symlink (Recommended)
```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html  # backup first!
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
```

- [ ] Backup existing `public_html` (if needed)
- [ ] Remove `public_html` directory
- [ ] Create symlink: `ln -s flexana/public public_html`
- [ ] Verify symlink: `ls -la public_html`

#### Option B: Move Public Contents
```bash
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html  # backup first!
cp -r flexana/public public_html
```

- [ ] Backup existing `public_html` (if needed)
- [ ] Copy `flexana/public` to `public_html`
- [ ] Edit `public_html/index.php` to update paths:
  - `__DIR__.'/../flexana/storage/framework/maintenance.php'`
  - `__DIR__.'/../flexana/vendor/autoload.php'`
  - `__DIR__.'/../flexana/bootstrap/app.php'`
- [ ] Verify `.htaccess` exists in `public_html`

---

## Post-Deployment

### Step 8: Create Users & Keys
- [ ] Create admin user: `php artisan make:filament-user`
  - Username: ________________
  - Email: ________________
  - Password: ________________ (stored securely)
- [ ] Generate API keys: `php artisan api:generate-key "Production App"`
  - API_KEY_ID: ________________
  - API_KEY_SECRET: ________________ (stored securely)

### Step 9: Configure Scheduled Tasks
- [ ] Add cron job (via cPanel or crontab):
```
* * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
```

- [ ] Verify cron job added correctly
- [ ] Test: `php artisan schedule:list`

### Step 10: Configure Queue Workers (if needed)
- [ ] Set up queue worker (supervisor or cron)
- [ ] Verify queue is processing: `php artisan queue:work --once`

---

## Verification & Testing

### Basic Functionality Tests
- [ ] Homepage loads: `https://admin-panel-flexana-egypt.com`
- [ ] Admin panel loads: `https://admin-panel-flexana-egypt.com/admin`
- [ ] Health check works: `https://admin-panel-flexana-egypt.com/up`
- [ ] Can log in to admin panel
- [ ] Assets loading correctly (CSS/JS)
- [ ] No console errors in browser

### API Tests
- [ ] Import Postman collection
- [ ] Test authentication endpoints
- [ ] Test CRUD operations
- [ ] Verify API responses

### Security Tests
- [ ] `.env` NOT accessible: `https://your-domain.com/.env` (should 404)
- [ ] `vendor/` NOT accessible: `https://your-domain.com/vendor/` (should 404)
- [ ] `storage/` NOT accessible: `https://your-domain.com/storage/` (should 404)
- [ ] HTTPS working (SSL certificate valid)
- [ ] Force HTTPS redirect working
- [ ] Admin panel requires authentication

### Performance Tests
- [ ] Page load time acceptable
- [ ] Database queries optimized
- [ ] Caching working correctly

### Logs Check
```bash
tail -f storage/logs/laravel.log
```

- [ ] No errors in Laravel logs
- [ ] No critical warnings
- [ ] Log rotation configured

---

## Configuration Files

### Environment Variables Verified
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` correct
- [ ] Database credentials correct
- [ ] Mail configuration correct
- [ ] Cache/session drivers configured
- [ ] Queue connection configured
- [ ] Firebase credentials (if using)

### Web Server Configuration
- [ ] Virtual host/server block configured (if applicable)
- [ ] SSL certificate installed
- [ ] Force HTTPS enabled
- [ ] Document root points to correct folder
- [ ] `.htaccess` file present and working

---

## Monitoring & Maintenance

### Set Up Monitoring
- [ ] Error tracking service configured (optional: Sentry, Bugsnag)
- [ ] Uptime monitoring configured (optional: UptimeRobot, Pingdom)
- [ ] Performance monitoring (optional: New Relic, Scout)

### Backup Strategy
- [ ] Database backup configured
- [ ] Application backup configured
- [ ] `.env` file backed up securely
- [ ] Storage directory backup configured
- [ ] Backup restoration tested

### Documentation
- [ ] Deployment process documented
- [ ] Admin credentials documented securely
- [ ] API keys documented securely
- [ ] Emergency contacts listed
- [ ] Rollback procedure documented

---

## Security Checklist

### Application Security
- [ ] `APP_DEBUG=false` in production ⚠️ CRITICAL
- [ ] `APP_ENV=production`
- [ ] Strong APP_KEY generated
- [ ] Strong database passwords
- [ ] API authentication working
- [ ] CSRF protection enabled
- [ ] XSS protection enabled
- [ ] SQL injection protection (using Eloquent ORM)

### Server Security
- [ ] SSH access secured (key-based auth preferred)
- [ ] Firewall configured
- [ ] Only necessary ports open (80, 443, 22)
- [ ] Regular security updates scheduled
- [ ] File permissions correct (755/644)
- [ ] `.env` not in version control
- [ ] `.git` directory not web accessible

### SSL/HTTPS
- [ ] SSL certificate installed
- [ ] Certificate valid and not expired
- [ ] Force HTTPS enabled
- [ ] HSTS header enabled (optional but recommended)
- [ ] Mixed content warnings resolved

---

## Troubleshooting Quick Reference

### 500 Internal Server Error
```bash
# Check permissions
chmod -R 775 storage bootstrap/cache

# Clear caches
php artisan config:clear
php artisan cache:clear

# Check logs
tail -f storage/logs/laravel.log
```

### Assets Not Loading (404)
```bash
# Rebuild assets
npm run build

# If using "move public contents" method
cp -r flexana/public/css public_html/
cp -r flexana/public/js public_html/
```

### Database Connection Error
- Verify database credentials in `.env`
- Test connection: `php artisan tinker` then `DB::connection()->getPdo();`
- Check if database server is running

### Permission Errors
```bash
# Fix ownership (replace 'www-data' with your user)
chown -R www-data:www-data storage bootstrap/cache

# Fix permissions
chmod -R 775 storage bootstrap/cache
```

---

## Rollback Procedure (If Needed)

If deployment fails and you need to rollback:

1. **Restore Previous Code:**
   ```bash
   cd /domains/admin-panel-flexana-egypt/flexana
   git checkout [previous-commit-hash]
   composer install --no-dev
   npm ci && npm run build
   ```

2. **Rollback Database:**
   ```bash
   php artisan migrate:rollback
   # Or restore database backup
   ```

3. **Clear Caches:**
   ```bash
   php artisan optimize:clear
   php artisan optimize
   ```

4. **Verify Site Working:**
   - Test homepage
   - Test admin panel
   - Check logs

---

## Support Contacts

### Technical Support
- Developer: ________________
- Email: ________________
- Phone: ________________

### Hosting Support
- Provider: ________________
- Support Email: ________________
- Support Phone: ________________
- Account Number: ________________

### Emergency Procedures
- Database admin contact: ________________
- Server admin contact: ________________
- After-hours contact: ________________

---

## Sign-Off

### Deployment Completed By
- Name: ________________
- Date: ________________
- Time: ________________
- Signature: ________________

### Verified By
- Name: ________________
- Date: ________________
- Time: ________________
- Signature: ________________

---

**Notes:**

Use this space for deployment-specific notes, issues encountered, or special configurations:

```
_______________________________________________________________

_______________________________________________________________

_______________________________________________________________

_______________________________________________________________

_______________________________________________________________
```

---

**Deployment Version:** ________________

**Git Commit Hash:** ________________

**Deployment Status:** [ ] Success  [ ] Partial  [ ] Failed

---

**Last Updated:** February 2026
