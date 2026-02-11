# Production Deployment to cPanel/Shared Hosting

## Overview
This guide will help you deploy the Flexana Laravel application to your production server where:
- Server path: `/domains/admin-panel-flexana-egypt/public_html/`
- Domain should point to the Laravel `public` folder

## Architecture
```
/home/flexanastudios/domains/admin-panel-flexana-egypt/
├── flexana/                    # Laravel application root (OUTSIDE public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/                 # This will be your web root
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   └── .env
└── public_html/                # Symlink or redirect to flexana/public
```

---

## Deployment Options

### Option 1: Symlink (Recommended - Cleaner)

This method keeps your Laravel app secure outside the web root and creates a symlink.

#### Step 1: Upload Files via SSH/SFTP

```bash
# Connect to your server via SSH
ssh your_username@your_server

# Navigate to the parent directory
cd /domains/admin-panel-flexana-egypt/

# Clone or upload your repository
git clone https://your-repo-url.git flexana
# OR upload via SFTP to /domains/admin-panel-flexana-egypt/flexana/

cd flexana
```

#### Step 2: Install Dependencies

```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev --no-interaction

# Install Node dependencies
npm ci

# Build frontend assets
npm run build
```

#### Step 3: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
```

Configure these essential variables:

```env
APP_NAME="Flexana"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin-panel-flexana-egypt.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_secure_password

# Set appropriate cache/session drivers
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=database

# Mail configuration
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@admin-panel-flexana-egypt.com

# Logging
LOG_CHANNEL=daily
LOG_LEVEL=error
```

#### Step 4: Generate Application Key

```bash
php artisan key:generate --force
```

#### Step 5: Run Database Migrations

```bash
php artisan migrate --force
```

#### Step 6: Set Permissions

```bash
# Set storage and cache permissions
chmod -R 775 storage bootstrap/cache

# If needed, adjust ownership (replace 'your_user' with your actual user)
chown -R your_user:your_user storage bootstrap/cache
```

#### Step 7: Link Storage

```bash
php artisan storage:link
```

#### Step 8: Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

#### Step 9: Create Symlink to Public Folder

```bash
# Remove the existing public_html directory (backup first if needed!)
cd /domains/admin-panel-flexana-egypt/
rm -rf public_html

# Create symlink from public_html to flexana/public
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Verify the symlink
ls -la public_html
# Should show: public_html -> /domains/admin-panel-flexana-egypt/flexana/public
```

#### Step 10: Verify Symlink and Files

```bash
# Check if symlink was created correctly
readlink public_html
# Should output: /domains/admin-panel-flexana-egypt/flexana/public

# Verify index.php exists in the target
ls -la /domains/admin-panel-flexana-egypt/flexana/public/index.php

# Test file access through symlink
ls -la public_html/index.php
```

**⚠️ If You Can't Reach the Project After This Step:**

**Problem 1: Symlink Not Supported**
```bash
# Test if symlinks work
cd /domains/admin-panel-flexana-egypt/
ls -la public_html

# If you see an error or symlink doesn't work, your hosting doesn't support symlinks
# Solution: Use Option 2 (Move Public Contents) instead
```

**Problem 2: Web Server Can't Follow Symlinks**

Edit your `.htaccess` file or check Apache configuration needs `FollowSymLinks`:
```bash
# Check if .htaccess exists
ls -la public_html/.htaccess

# Add this to .htaccess if missing:
echo "Options +FollowSymLinks" >> public_html/.htaccess
```

**Problem 3: Permissions Issue**
```bash
# Fix permissions on the symlink target
chmod 755 /domains/admin-panel-flexana-egypt/flexana/public
chmod 644 /domains/admin-panel-flexana-egypt/flexana/public/index.php
chmod 644 /domains/admin-panel-flexana-egypt/flexana/public/.htaccess
```

**Problem 4: Web Server Not Restarted**
```bash
# If you have access, restart Apache
sudo systemctl restart apache2
# or
sudo service apache2 restart

# For cPanel, web server usually auto-restarts
```

---

### Option 2: Move Public Contents (Alternative)

If your hosting doesn't support symlinks, use this method:

#### Step 1-8: Same as Option 1 (Upload, Install, Configure)

Follow Steps 1-8 from Option 1 above.

#### Step 9: Move Public Directory Contents

```bash
cd /domains/admin-panel-flexana-egypt/

# Backup existing public_html if needed
mv public_html public_html_backup

# Copy public folder contents to public_html
cp -r flexana/public public_html

cd public_html
```

#### Step 10: Update index.php

Edit `/domains/admin-panel-flexana-egypt/public_html/index.php`:

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

The key changes are updating the paths:
- `__DIR__.'/../flexana/storage/framework/maintenance.php'`
- `__DIR__.'/../flexana/vendor/autoload.php'`
- `__DIR__.'/../flexana/bootstrap/app.php'`

---

## Troubleshooting: Can't Reach Project After Symlink

If you've created the symlink but can't access your site, follow these diagnostic steps:

### Step 1: Verify Symlink Created Correctly

```bash
cd /domains/admin-panel-flexana-egypt/

# Check symlink
ls -la public_html
# Should show: public_html -> /domains/admin-panel-flexana-egypt/flexana/public

# If it shows a regular directory, the symlink wasn't created
# Solution: Remove and recreate
rm -rf public_html
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html
```

### Step 2: Check if Hosting Supports Symlinks

```bash
# Test symlink support
cd /domains/admin-panel-flexana-egypt/
echo "test" > test_file.txt
ln -s test_file.txt test_link.txt
cat test_link.txt

# If this works, symlinks are supported
# If error, contact your hosting provider or use Option 2
rm test_file.txt test_link.txt
```

### Step 3: Verify Files Exist in Target Location

```bash
# Check if files exist where symlink points
ls -la /domains/admin-panel-flexana-egypt/flexana/public/

# Verify index.php exists
cat /domains/admin-panel-flexana-egypt/flexana/public/index.php | head -n 5

# Verify .htaccess exists
cat /domains/admin-panel-flexana-egypt/flexana/public/.htaccess
```

### Step 4: Check File Permissions

```bash
# Check public directory permissions
ls -ld /domains/admin-panel-flexana-egypt/flexana/public/
# Should show: drwxr-xr-x (755)

# Fix if needed
chmod 755 /domains/admin-panel-flexana-egypt/flexana/public/
chmod 644 /domains/admin-panel-flexana-egypt/flexana/public/index.php
chmod 644 /domains/admin-panel-flexana-egypt/flexana/public/.htaccess
```

### Step 5: Check Apache/Web Server Configuration

```bash
# Verify .htaccess is present and readable
ls -la /domains/admin-panel-flexana-egypt/flexana/public/.htaccess

# Check .htaccess content
head /domains/admin-panel-flexana-egypt/flexana/public/.htaccess
```

If `.htaccess` is missing or empty:
```bash
cd /domains/admin-panel-flexana-egypt/flexana/public/
cat > .htaccess << 'EOF'
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
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
EOF
```

### Step 6: Check Laravel Application Status

```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Check if .env exists
ls -la .env

# Verify APP_KEY is set
grep APP_KEY .env

# Clear and rebuild caches
php artisan optimize:clear
php artisan optimize

# Check for errors
php artisan about
```

### Step 7: Test Direct File Access

```bash
# Create a test PHP file
echo "<?php phpinfo(); ?>" > /domains/admin-panel-flexana-egypt/flexana/public/test.php

# Try to access it via browser
# Visit: https://admin-panel-flexana-egypt.com/test.php

# If this works, PHP is running but Laravel might have issues
# If this doesn't work, web server isn't reading from the symlink

# Remove test file after testing
rm /domains/admin-panel-flexana-egypt/flexana/public/test.php
```

### Step 8: Check Error Logs

```bash
# Check Laravel logs
tail -n 50 /domains/admin-panel-flexana-egypt/flexana/storage/logs/laravel.log

# Check Apache error logs (if you have access)
tail -n 50 /var/log/apache2/error.log
# or
tail -n 50 /usr/local/apache/logs/error_log

# For cPanel, check error logs in cPanel interface
```

### Solution: If Symlinks Don't Work, Use Option 2

If none of the above works, your hosting likely doesn't support symlinks. Use this alternative:

```bash
cd /domains/admin-panel-flexana-egypt/

# Remove symlink
rm -rf public_html

# Copy public folder contents
cp -r flexana/public public_html

# Edit index.php to point to Laravel app
nano public_html/index.php
```

**In `public_html/index.php`, change these 3 paths:**

Find and replace:
```php
// FIND:
__DIR__.'/../storage/framework/maintenance.php'
__DIR__.'/../vendor/autoload.php'
__DIR__.'/../bootstrap/app.php'

// REPLACE WITH:
__DIR__.'/../flexana/storage/framework/maintenance.php'
__DIR__.'/../flexana/vendor/autoload.php'
__DIR__.'/../flexana/bootstrap/app.php'
```

**Complete modified `index.php`:**
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

After making these changes:
```bash
# Test the site
curl -I https://admin-panel-flexana-egypt.com

# Check logs
tail -f /domains/admin-panel-flexana-egypt/flexana/storage/logs/laravel.log
```

---

## Using the Automated Deployment Script

You can also use the provided `deploy.sh` script:

```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Make it executable
chmod +x deploy.sh

# Run deployment
./deploy.sh production
```

The script will:
- Check prerequisites (PHP, Composer, Node.js)
- Install dependencies
- Build assets
- Run migrations (with confirmation)
- Set permissions
- Link storage
- Cache configuration
- Optimize the application

---

## Post-Deployment Tasks

### 1. Create Admin User

```bash
cd /domains/admin-panel-flexana-egypt/flexana
php artisan make:filament-user
```

Follow the prompts to create your admin account.

### 2. Generate API Keys (if needed)

```bash
php artisan api:generate-key "Production Frontend App"
```

Save the generated `API_KEY_ID` and `API_KEY_SECRET` securely.

### 3. Test the Application

1. Visit your domain: `https://admin-panel-flexana-egypt.com`
2. Test the admin panel: `https://admin-panel-flexana-egypt.com/admin`
3. Check health endpoint: `https://admin-panel-flexana-egypt.com/up`
4. Test API endpoints with the Postman collection

### 4. Set Up Scheduled Tasks (Cron Jobs)

Add to crontab (via cPanel or command line):

```cron
* * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
```

In cPanel:
1. Go to "Cron Jobs"
2. Add new cron job with command:
   ```
   * * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1
   ```

### 5. Configure Queue Workers (if needed)

For background jobs, you might need to set up a queue worker. Check with your hosting provider about long-running processes.

If allowed, create a supervisor configuration or use a cronjob:

```cron
* * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

---

## Troubleshooting

### Issue: 500 Internal Server Error

**Solution:**
1. Check file permissions:
   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

2. Check `.env` file exists and is configured correctly

3. Check error logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. Clear all caches:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   ```

### Issue: Assets Not Loading (CSS/JS)

**Solution:**
1. Rebuild assets:
   ```bash
   npm run build
   ```

2. Check if `public/js` and `public/css` directories exist

3. Clear browser cache

4. Check `.htaccess` file exists in public folder

### Issue: Database Connection Error

**Solution:**
1. Verify database credentials in `.env`
2. Test database connection:
   ```bash
   php artisan tinker
   >>> DB::connection()->getPdo();
   ```

3. Ensure database user has proper permissions

### Issue: Public Folder Not Accessible

**Solution:**
1. Verify symlink is created correctly:
   ```bash
   ls -la /domains/admin-panel-flexana-egypt/public_html
   ```

2. Check if your hosting allows symlinks

3. If symlinks not supported, use Option 2 (Move Public Contents)

### Issue: Storage Link Not Working

**Solution:**
```bash
# Remove existing symlink
rm public/storage

# Recreate it
php artisan storage:link

# Verify
ls -la public/storage
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production `.env`
- [ ] `APP_ENV=production` in `.env`
- [ ] Strong database password set
- [ ] SSL certificate installed (HTTPS enabled)
- [ ] `.env` file is NOT in web root (should be in `/flexana/`, not `/public_html/`)
- [ ] Storage directory not publicly accessible
- [ ] File permissions: 755 for directories, 644 for files
- [ ] Storage/cache: 775 permissions
- [ ] Regular backups configured
- [ ] Error logging enabled (but not displayed to users)

---

## Maintenance & Updates

### Updating the Application

```bash
cd /domains/admin-panel-flexana-egypt/flexana

# Pull latest changes
git pull origin main

# Install updated dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Run new migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Backup Strategy

Regular backups should include:
1. Database: `mysqldump -u username -p database_name > backup.sql`
2. `.env` file
3. `storage/` directory (uploaded files)
4. Application code (via Git)

---

## Quick Reference Commands

```bash
# Navigate to application
cd /domains/admin-panel-flexana-egypt/flexana

# Clear all caches
php artisan optimize:clear

# Re-optimize application
php artisan optimize

# Check application status
php artisan about

# View routes
php artisan route:list

# View logs
tail -f storage/logs/laravel.log

# Create admin user
php artisan make:filament-user

# Generate API key
php artisan api:generate-key "App Name"

# Run migrations
php artisan migrate --force

# Run database seeder
php artisan db:seed
```

---

## Support

If you encounter issues:
1. Check `storage/logs/laravel.log` for detailed errors
2. Review Laravel documentation: https://laravel.com/docs
3. Review Filament documentation: https://filamentphp.com/docs

---

**Deployment Checklist:**

- [ ] Repository uploaded/cloned to `/domains/admin-panel-flexana-egypt/flexana/`
- [ ] Dependencies installed (Composer & NPM)
- [ ] `.env` file configured with production values
- [ ] Application key generated
- [ ] Database migrations run
- [ ] Storage permissions set (775)
- [ ] Storage linked
- [ ] Application optimized (caches created)
- [ ] Symlink created: `public_html -> flexana/public`
- [ ] Admin user created
- [ ] Domain accessible via HTTPS
- [ ] Cron jobs configured
- [ ] Backups configured
- [ ] SSL certificate installed

---

**Last Updated:** February 2026
