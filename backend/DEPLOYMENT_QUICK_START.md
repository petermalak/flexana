# Quick Deployment Reference

## One-Command Deployment (Linux)

```bash
cd /var/www/flexana/backend
./deploy.sh production
```

## Manual Deployment Steps

### 1. Install Dependencies
```bash
composer install --optimize-autoloader --no-dev
npm ci && npm run build
```

### 2. Configure Environment
```bash
cp .env.example .env
nano .env  # Edit with production values
php artisan key:generate
```

### 3. Set Permissions
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 4. Run Migrations
```bash
php artisan migrate --force
```

### 5. Optimize
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

### 6. Generate API Keys
```bash
php artisan api:generate-key "Production Frontend App"
```

## Essential .env Settings

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=flexana_production
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

## Post-Deployment Checklist

- [ ] Test health endpoint: `https://yourdomain.com/up`
- [ ] Access admin panel: `https://yourdomain.com/admin`
- [ ] Generate API keys for frontend
- [ ] Set up queue workers (Supervisor)
- [ ] Configure cron for scheduled tasks
- [ ] Set up log rotation
- [ ] Configure SSL certificate
- [ ] Test API endpoints

## Common Commands

```bash
# Clear all caches
php artisan optimize:clear

# Re-optimize
php artisan optimize

# View logs
tail -f storage/logs/laravel.log

# Check application status
php artisan about
```

## Troubleshooting

**500 Error**: Check permissions and logs
```bash
chmod -R 775 storage bootstrap/cache
tail -f storage/logs/laravel.log
```

**Assets not loading**: Rebuild assets
```bash
npm run build
```

**Database errors**: Check .env configuration
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

For detailed instructions, see [DEPLOYMENT.md](./DEPLOYMENT.md)







