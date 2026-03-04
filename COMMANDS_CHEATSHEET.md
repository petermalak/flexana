# Flexana Deployment Commands Cheatsheet

Quick reference for all deployment commands.

---

## 📁 Navigation

```bash
# Navigate to application
cd /domains/admin-panel-flexana-egypt/flexana

# Check current directory
pwd

# List files
ls -la

# Check disk space
df -h
```

---

## 📦 Installation & Dependencies

### PHP Dependencies
```bash
# Install production dependencies
composer install --optimize-autoloader --no-dev --no-interaction

# Install all dependencies (including dev)
composer install --optimize-autoloader

# Update dependencies
composer update --no-dev

# Dump autoloader
composer dump-autoload -o
```

### Node Dependencies
```bash
# Install exact versions from package-lock.json
npm ci

# Install dependencies
npm install

# Install and build
npm ci && npm run build

# Build assets for production
npm run build

# Build for development (with source maps)
npm run dev
```

---

## 🔑 Environment & Keys

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
# or
vim .env

# Generate application key
php artisan key:generate

# Force key generation (production)
php artisan key:generate --force

# View configuration
php artisan config:show app
php artisan config:show database
```

---

## 🗄️ Database

### Migrations
```bash
# Run migrations
php artisan migrate

# Run migrations (production, no confirmation)
php artisan migrate --force

# Run migrations with output
php artisan migrate --verbose

# Rollback last migration
php artisan migrate:rollback

# Rollback all migrations
php artisan migrate:reset

# Rollback and re-run
php artisan migrate:refresh

# Check migration status
php artisan migrate:status

# Create new migration
php artisan make:migration create_table_name
```

### Seeding
```bash
# Run database seeders
php artisan db:seed

# Run specific seeder
php artisan db:seed --class=UserSeeder

# Refresh and seed
php artisan migrate:refresh --seed
```

### Database Testing
```bash
# Test connection via tinker
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Check database
php artisan db:show

# Check specific table
php artisan db:table users
```

### Amelia / WordPress data fetch (staging & production)

Fetch all Amelia (WordPress) data into the Laravel MySQL database. Requires `wordpress` DB connection in `.env` (see MIGRATION_STEPS_ON_SERVER.md).

```bash
# Full fetch (run on staging or production after .env is set)
php artisan amelia:fetch

# Dry run (no writes)
php artisan amelia:fetch --dry-run

# Skip records that already exist
php artisan amelia:fetch --skip-duplicates

# Only specific entities
php artisan amelia:fetch --only=locations --only=customers --only=appointments
```

Required `.env` vars: `WP_DB_HOST`, `WP_DB_DATABASE`, `WP_DB_USERNAME`, `WP_DB_PASSWORD`. Set `WP_DB_PREFIX` to match Amelia tables (e.g. `rueyn_amelia_` if tables are `rueyn_amelia_users`, `rueyn_amelia_services`, etc.).

### Fetch WordPress mail config into .env

Reads mail/SMTP settings from the WordPress database (same as `WP_DB_*`) and prints suggested `MAIL_*` lines for `.env`:

```bash
# Use options table from .env (WP_OPTIONS_TABLE, default wp_options)
php artisan wp:mail-config

# Override options table (e.g. if WordPress uses rueyn_options)
php artisan wp:mail-config --table=rueyn_options

# Show raw option_name / option_value for all mail-related options
php artisan wp:mail-config --show-raw
```

Optional `.env`: `WP_OPTIONS_TABLE=rueyn_options` if your WordPress options table is not `wp_options`.

---

## 🔐 Permissions

```bash
# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Set ownership (adjust user/group)
chown -R www-data:www-data storage
chown -R www-data:www-data bootstrap/cache

# Set all directory permissions
find . -type d -exec chmod 755 {} \;

# Set all file permissions
find . -type f -exec chmod 644 {} \;

# Make scripts executable
chmod +x deploy.sh
chmod +x deploy-production.sh
```

---

## 🔗 Storage

```bash
# Create storage link
php artisan storage:link

# Remove storage link
rm public/storage

# Recreate storage link
rm public/storage && php artisan storage:link

# Check if storage is linked
ls -la public/storage
```

---

## 🚀 Optimization

### Cache Management
```bash
# Cache everything
php artisan optimize

# Clear all caches
php artisan optimize:clear

# Cache configuration
php artisan config:cache

# Clear configuration cache
php artisan config:clear

# Cache routes
php artisan route:cache

# Clear route cache
php artisan route:clear

# Cache views
php artisan view:cache

# Clear view cache
php artisan view:clear

# Cache events
php artisan event:cache

# Clear event cache
php artisan event:clear

# Clear application cache
php artisan cache:clear
```

### Production Optimization
```bash
# Complete production optimization
php artisan config:cache && \
php artisan route:cache && \
php artisan view:cache && \
php artisan event:cache

# Clear everything and re-optimize
php artisan optimize:clear && php artisan optimize
```

---

## 🌐 Web Root Configuration

### Symlink Method
```bash
# Navigate to parent directory
cd /domains/admin-panel-flexana-egypt/

# Backup existing public_html (optional)
mv public_html public_html_backup_$(date +%Y%m%d_%H%M%S)

# Remove public_html
rm -rf public_html

# Create symlink
ln -s /domains/admin-panel-flexana-egypt/flexana/public public_html

# Verify symlink
ls -la public_html
# Should show: public_html -> /domains/admin-panel-flexana-egypt/flexana/public

# Test symlink
readlink public_html
```

### Move Public Contents Method
```bash
# Navigate to parent directory
cd /domains/admin-panel-flexana-egypt/

# Backup existing public_html (optional)
mv public_html public_html_backup_$(date +%Y%m%d_%H%M%S)

# Copy public folder
cp -r flexana/public public_html

# Edit index.php (manual step)
nano public_html/index.php
# Update paths to point to ../flexana/

# Copy updated assets after rebuild
cp -r flexana/public/css public_html/
cp -r flexana/public/js public_html/
cp -r flexana/public/images public_html/
```

---

## 👤 User Management

### Filament Admin Users
```bash
# Create admin user (interactive)
php artisan make:filament-user

# Create user via tinker
php artisan tinker
>>> $user = App\Models\User::create([
...   'name' => 'Admin',
...   'email' => 'admin@example.com',
...   'password' => bcrypt('password')
... ]);
>>> exit
```

### API Keys
```bash
# Generate API key
php artisan api:generate-key "Production App"

# List API keys (if command exists)
php artisan api:list-keys
```

---

## 📊 Information & Debugging

### Application Info
```bash
# Show application information
php artisan about

# List all routes
php artisan route:list

# List routes for specific domain
php artisan route:list --domain=api

# List specific route
php artisan route:list --name=login

# Show Laravel version
php artisan --version

# List all artisan commands
php artisan list
```

### Logs
```bash
# View Laravel logs (last 50 lines)
tail -n 50 storage/logs/laravel.log

# Follow logs in real-time
tail -f storage/logs/laravel.log

# View logs with grep
tail -f storage/logs/laravel.log | grep ERROR

# Clear log file
> storage/logs/laravel.log

# View log file size
du -h storage/logs/laravel.log
```

### Debug Mode (Development Only!)
```bash
# Enable debug mode (NEVER in production!)
# Edit .env file:
APP_DEBUG=true

# Disable debug mode (production)
APP_DEBUG=false
```

---

## 🔄 Queue & Jobs

```bash
# Process queue once
php artisan queue:work --once

# Start queue worker
php artisan queue:work

# Start queue with settings
php artisan queue:work --sleep=3 --tries=3 --max-time=3600

# Listen to queue
php artisan queue:listen

# List failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry [job-id]

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush

# Restart queue workers
php artisan queue:restart
```

---

## ⏰ Scheduled Tasks

```bash
# List scheduled tasks
php artisan schedule:list

# Run scheduled tasks (manual)
php artisan schedule:run

# Test schedule
php artisan schedule:test

# Add to crontab
crontab -e
# Add this line:
# * * * * * cd /domains/admin-panel-flexana-egypt/flexana && php artisan schedule:run >> /dev/null 2>&1

# View current cron jobs
crontab -l
```

---

## 🧪 Testing & Verification

### Health Checks
```bash
# Test application is accessible
curl https://admin-panel-flexana-egypt.com/up

# Test with headers
curl -I https://admin-panel-flexana-egypt.com

# Test specific route
curl https://admin-panel-flexana-egypt.com/api/v1/health
```

### PHP Version & Extensions
```bash
# Check PHP version
php -v

# Check installed PHP modules
php -m

# Check specific extension
php -m | grep -i mbstring

# PHP info
php -i | less

# Check PHP configuration
php --ini
```

### Composer & Node
```bash
# Check Composer version
composer --version

# Diagnose Composer issues
composer diagnose

# Check Node.js version
node -v

# Check NPM version
npm -v

# Check installed global packages
npm list -g --depth=0
```

### File System
```bash
# Check disk usage
df -h

# Check directory size
du -sh storage/

# Check file permissions
ls -la storage/
ls -la bootstrap/cache/

# Find large files
find storage/ -type f -size +10M -exec ls -lh {} \;
```

---

## 🔍 Troubleshooting Commands

### Clear Everything
```bash
# Nuclear option - clear everything
php artisan optimize:clear && \
php artisan config:clear && \
php artisan cache:clear && \
php artisan route:clear && \
php artisan view:clear && \
php artisan event:clear

# Then re-optimize
php artisan optimize
```

### Fix Permissions
```bash
# Fix storage permissions
chmod -R 775 storage bootstrap/cache

# Fix ownership
chown -R www-data:www-data storage bootstrap/cache

# Reset all permissions
find . -type f -exec chmod 644 {} \;
find . -type d -exec chmod 755 {} \;
chmod -R 775 storage bootstrap/cache
```

### Rebuild Assets
```bash
# Remove node_modules and rebuild
rm -rf node_modules
npm ci
npm run build

# Remove public assets and rebuild
rm -rf public/css public/js
npm run build
```

### Database Issues
```bash
# Clear database cache
php artisan cache:clear

# Check connection
php artisan tinker
>>> DB::connection()->getPdo();

# Test query
php artisan tinker
>>> DB::table('users')->count();
```

---

## 📦 Deployment Scripts

### Run Deployment Script
```bash
# Make executable
chmod +x deploy-production.sh

# Run production deployment
./deploy-production.sh

# Run standard deployment
chmod +x deploy.sh
./deploy.sh production
```

### Git Deployment
```bash
# Pull latest changes
git pull origin main

# Check current branch
git branch

# Check git status
git status

# View last commit
git log -1
```

---

## 🛡️ Security Checks

### Verify Configuration
```bash
# Check APP_DEBUG is false
grep APP_DEBUG .env
# Should show: APP_DEBUG=false

# Check APP_ENV is production
grep APP_ENV .env
# Should show: APP_ENV=production

# Check APP_KEY is set
grep APP_KEY .env
# Should show: APP_KEY=base64:...
```

### Test Security
```bash
# These should return 404 or 403
curl https://admin-panel-flexana-egypt.com/.env
curl https://admin-panel-flexana-egypt.com/vendor/
curl https://admin-panel-flexana-egypt.com/storage/

# Check HTTPS redirect
curl -I http://admin-panel-flexana-egypt.com
# Should show: Location: https://...
```

---

## 💾 Backup Commands

### Database Backup
```bash
# Backup database
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore database
mysql -u username -p database_name < backup.sql
```

### File Backup
```bash
# Backup entire application
cd /domains/admin-panel-flexana-egypt/
tar -czf flexana_backup_$(date +%Y%m%d_%H%M%S).tar.gz flexana/

# Backup only essential files
tar -czf flexana_essential_$(date +%Y%m%d_%H%M%S).tar.gz \
  flexana/.env \
  flexana/storage/ \
  flexana/database/

# Extract backup
tar -xzf flexana_backup_20260211_150000.tar.gz
```

---

## 🚨 Emergency Commands

### Quick Rollback
```bash
# Go back to previous commit
git reset --hard HEAD^
composer install --no-dev
npm ci && npm run build
php artisan migrate:rollback
php artisan optimize
```

### Maintenance Mode
```bash
# Enable maintenance mode
php artisan down

# Enable with message
php artisan down --message="System maintenance" --retry=60

# Disable maintenance mode
php artisan up

# Check if in maintenance mode
php artisan optimize:clear && curl https://admin-panel-flexana-egypt.com
```

### Quick Fix 500 Error
```bash
chmod -R 775 storage bootstrap/cache
php artisan optimize:clear
tail -f storage/logs/laravel.log
```

---

## 📝 One-Liner Combinations

```bash
# Complete fresh deployment
composer install --no-dev && npm ci && npm run build && \
php artisan key:generate --force && php artisan migrate --force && \
chmod -R 775 storage bootstrap/cache && php artisan storage:link && \
php artisan optimize

# Update and optimize
git pull && composer install --no-dev && npm ci && npm run build && \
php artisan migrate --force && php artisan optimize:clear && php artisan optimize

# Clear everything and test
php artisan optimize:clear && php artisan cache:clear && \
php artisan config:cache && php artisan route:cache && \
php artisan view:cache && tail -f storage/logs/laravel.log

# Emergency fix
chmod -R 775 storage bootstrap/cache && php artisan optimize:clear && \
php artisan optimize && systemctl restart apache2
```

---

**Tip:** Save this cheatsheet for quick reference during deployment!
