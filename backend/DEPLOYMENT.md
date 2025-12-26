# Flexana Production Deployment Guide

This guide covers deploying the Flexana Laravel application to production.

## Table of Contents
1. [Server Requirements](#server-requirements)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Environment Configuration](#environment-configuration)
4. [Deployment Steps](#deployment-steps)
5. [Post-Deployment Tasks](#post-deployment-tasks)
6. [Troubleshooting](#troubleshooting)

---

## Server Requirements

### Minimum Requirements
- **PHP**: 8.2 or higher
- **Composer**: Latest version
- **Node.js**: 18.x or higher (for building assets)
- **NPM**: 9.x or higher
- **Database**: MySQL 8.0+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Extensions Required**:
  - BCMath
  - Ctype
  - cURL
  - DOM
  - Fileinfo
  - JSON
  - Mbstring
  - OpenSSL
  - PCRE
  - PDO
  - Tokenizer
  - XML

### Recommended Server Configuration
- **Memory**: 512MB minimum (1GB+ recommended)
- **Disk Space**: 2GB+ for application and dependencies
- **SSL Certificate**: Required for production (Let's Encrypt recommended)

---

## Pre-Deployment Checklist

- [ ] All code changes committed and pushed to repository
- [ ] Database migrations tested locally
- [ ] Environment variables documented
- [ ] API keys generated for frontend integration
- [ ] SSL certificate obtained and configured
- [ ] Database backup strategy in place
- [ ] Log rotation configured
- [ ] Monitoring and error tracking set up
- [ ] Backup storage location configured

---

## Environment Configuration

### 1. Create Production Environment File

Copy `.env.example` to `.env` and configure the following:

```bash
cp .env.example .env
```

### 2. Essential Environment Variables

#### Application Settings
```env
APP_NAME="Flexana"
APP_ENV=production
APP_KEY=base64:YOUR_APPLICATION_KEY_HERE
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=UTC
```

#### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=flexana_production
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password
```

#### Cache & Session
```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Mail Configuration
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_mail_username
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

#### File Storage
```env
FILESYSTEM_DISK=local
# Or for S3:
# FILESYSTEM_DISK=s3
# AWS_ACCESS_KEY_ID=your_key
# AWS_SECRET_ACCESS_KEY=your_secret
# AWS_DEFAULT_REGION=us-east-1
# AWS_BUCKET=your-bucket-name
```

#### Logging
```env
LOG_CHANNEL=daily
LOG_LEVEL=error
LOG_DEPRECATIONS_CHANNEL=null
```

#### Firebase (if using Firebase Auth)
```env
FIREBASE_PROJECT_ID=your_project_id
FIREBASE_PRIVATE_KEY_ID=your_key_id
FIREBASE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
FIREBASE_CLIENT_EMAIL=your_client_email
FIREBASE_CLIENT_ID=your_client_id
FIREBASE_AUTH_URI=https://accounts.google.com/o/oauth2/auth
FIREBASE_TOKEN_URI=https://oauth2.googleapis.com/token
```

---

## Deployment Steps

### Option 1: Manual Deployment

#### Step 1: Clone Repository
```bash
cd /var/www
git clone https://your-repo-url.git flexana
cd flexana/backend
```

#### Step 2: Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Install Node dependencies
npm ci

# Build assets
npm run build
```

#### Step 3: Configure Environment
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Edit .env file with production values
nano .env
```

#### Step 4: Set Permissions
```bash
# Set ownership (adjust user/group as needed)
sudo chown -R www-data:www-data /var/www/flexana/backend

# Set directory permissions
sudo find /var/www/flexana/backend -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/flexana/backend -type f -exec chmod 644 {} \;

# Set storage and cache permissions
sudo chmod -R 775 /var/www/flexana/backend/storage
sudo chmod -R 775 /var/www/flexana/backend/bootstrap/cache
```

#### Step 5: Run Migrations
```bash
# Run database migrations
php artisan migrate --force

# Optionally seed initial data (if needed)
# php artisan db:seed --class=DatabaseSeeder
```

#### Step 6: Optimize Application
```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Cache events
php artisan event:cache
```

#### Step 7: Link Storage (if using public storage)
```bash
php artisan storage:link
```

### Option 2: Using Deployment Script

Use the provided `deploy.sh` script:

```bash
chmod +x deploy.sh
./deploy.sh production
```

---

## Post-Deployment Tasks

### 1. Generate API Keys for Frontend

```bash
php artisan api:generate-key "Production Frontend App"
```

Save the generated `API_KEY_ID` and `API_KEY_SECRET` securely and share with frontend team.

### 2. Create Admin User (if needed)

If you need to create an admin user for Filament:

```bash
php artisan make:filament-user
```

### 3. Set Up Queue Worker (if using queues)

```bash
# Using Supervisor (recommended)
sudo nano /etc/supervisor/conf.d/flexana-worker.conf
```

Add:
```ini
[program:flexana-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/flexana/backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/flexana/backend/storage/logs/worker.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start flexana-worker:*
```

### 4. Set Up Scheduled Tasks (Cron)

Add to crontab:
```bash
sudo crontab -e -u www-data
```

Add:
```cron
* * * * * cd /var/www/flexana/backend && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Configure Web Server

#### Apache Configuration

Create virtual host: `/etc/apache2/sites-available/flexana.conf`

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/flexana/backend/public

    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    SSLCertificateChainFile /path/to/chain.crt

    <Directory /var/www/flexana/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/flexana_error.log
    CustomLog ${APACHE_LOG_DIR}/flexana_access.log combined
</VirtualHost>
```

Enable site:
```bash
sudo a2ensite flexana.conf
sudo a2enmod rewrite ssl
sudo systemctl restart apache2
```

#### Nginx Configuration

Create: `/etc/nginx/sites-available/flexana`

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/flexana/backend/public;

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

Enable site:
```bash
sudo ln -s /etc/nginx/sites-available/flexana /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 6. Set Up Log Rotation

Create: `/etc/logrotate.d/flexana`

```
/var/www/flexana/backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm > /dev/null 2>&1 || true
    endscript
}
```

### 7. Verify Deployment

1. **Health Check**: Visit `https://yourdomain.com/up`
2. **Admin Panel**: Visit `https://yourdomain.com/admin`
3. **API Test**: Test API endpoints with Postman collection
4. **Check Logs**: `tail -f storage/logs/laravel.log`

---

## Troubleshooting

### Common Issues

#### 1. 500 Internal Server Error
- Check file permissions: `storage/` and `bootstrap/cache/` should be writable
- Check `.env` file exists and is configured correctly
- Check logs: `storage/logs/laravel.log`
- Clear caches: `php artisan config:clear && php artisan cache:clear`

#### 2. Database Connection Error
- Verify database credentials in `.env`
- Ensure database server is running
- Check database user has proper permissions
- Verify firewall allows connections

#### 3. Assets Not Loading
- Run `npm run build` to rebuild assets
- Check `public/build` directory exists
- Verify web server can access `public/` directory
- Clear browser cache

#### 4. Permission Denied Errors
- Check ownership: `ls -la storage/`
- Fix permissions: `chmod -R 775 storage bootstrap/cache`
- Fix ownership: `chown -R www-data:www-data storage bootstrap/cache`

#### 5. API Signature Authentication Failing
- Verify API keys are generated: `php artisan api:generate-key`
- Check frontend is sending correct headers
- Verify timestamp is within 5 minutes
- Check IP whitelist if configured

### Useful Commands

```bash
# Clear all caches
php artisan optimize:clear

# Re-optimize application
php artisan optimize

# Check application status
php artisan about

# View routes
php artisan route:list

# Check queue status
php artisan queue:work --once

# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` set
- [ ] Strong database passwords
- [ ] SSL certificate installed and configured
- [ ] File permissions set correctly (755 for dirs, 644 for files)
- [ ] Storage directory not publicly accessible
- [ ] `.env` file not in web root
- [ ] API keys stored securely
- [ ] Regular security updates applied
- [ ] Firewall configured
- [ ] Backup strategy in place
- [ ] Error logging configured (not displaying to users)

---

## Maintenance

### Regular Tasks

1. **Daily**: Monitor logs for errors
2. **Weekly**: Review and rotate logs
3. **Monthly**: Update dependencies (`composer update`, `npm update`)
4. **Quarterly**: Review and update security patches

### Updating Application

```bash
# Pull latest changes
git pull origin main

# Install new dependencies
composer install --optimize-autoloader --no-dev
npm ci && npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan optimize:clear
php artisan optimize
```

---

## Support

For issues or questions:
- Check logs: `storage/logs/laravel.log`
- Review Laravel documentation: https://laravel.com/docs
- Review Filament documentation: https://filamentphp.com/docs

---

**Last Updated**: December 2024







