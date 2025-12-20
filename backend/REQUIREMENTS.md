# Server Requirements for Flexana

## Minimum System Requirements

### Operating System
- **Linux**: Ubuntu 20.04 LTS or higher, Debian 11+, CentOS 8+, or similar
- **Windows Server**: Windows Server 2019+ (for development/testing only)
- **macOS**: macOS 12+ (for development only)

### PHP Requirements
- **Version**: PHP 8.2 or higher
- **Required Extensions**:
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
  - PDO_MySQL
  - Tokenizer
  - XML
  - Zip

### Database
- **MySQL**: 8.0 or higher
- **MariaDB**: 10.3 or higher
- **Alternative**: PostgreSQL 13+ (requires configuration changes)

### Web Server
- **Apache**: 2.4+ with mod_rewrite enabled
- **Nginx**: 1.18+ with PHP-FPM
- **Alternative**: Any web server supporting PHP-FPM

### Additional Software
- **Composer**: Latest version (PHP dependency manager)
- **Node.js**: 18.x or higher
- **NPM**: 9.x or higher

### Recommended Server Specifications

#### Small Deployment (Up to 100 concurrent users)
- **CPU**: 2 cores
- **RAM**: 2GB
- **Storage**: 20GB SSD
- **Bandwidth**: 100Mbps

#### Medium Deployment (100-500 concurrent users)
- **CPU**: 4 cores
- **RAM**: 4GB
- **Storage**: 50GB SSD
- **Bandwidth**: 1Gbps

#### Large Deployment (500+ concurrent users)
- **CPU**: 8+ cores
- **RAM**: 8GB+
- **Storage**: 100GB+ SSD
- **Bandwidth**: 10Gbps

### Optional but Recommended

#### Redis (Recommended for Production)
- **Version**: 6.0 or higher
- **Purpose**: Caching, sessions, queues
- **Memory**: 512MB minimum

#### Supervisor (For Queue Workers)
- **Version**: 4.0 or higher
- **Purpose**: Managing background queue workers

#### SSL/TLS Certificate
- **Required**: Yes (for production)
- **Options**: Let's Encrypt (free), Commercial certificates
- **Protocol**: TLS 1.2 or higher

### PHP Configuration Recommendations

#### php.ini Settings
```ini
memory_limit = 256M
upload_max_filesize = 20M
post_max_size = 20M
max_execution_time = 300
max_input_time = 300
date.timezone = UTC
```

#### OPcache (Recommended)
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### Security Requirements

- **Firewall**: Configured to allow only necessary ports (80, 443, 22)
- **SSH**: Key-based authentication recommended
- **File Permissions**: Proper ownership and permissions set
- **Regular Updates**: System and application updates scheduled

### Network Requirements

- **Ports to Open**:
  - 80 (HTTP) - Redirect to HTTPS
  - 443 (HTTPS) - Main application
  - 22 (SSH) - Server management
  - 3306 (MySQL) - Only if remote access needed
  - 6379 (Redis) - Only if remote access needed

### Monitoring Recommendations

- **Application Monitoring**: Error tracking (Sentry, Bugsnag, etc.)
- **Server Monitoring**: CPU, RAM, Disk usage
- **Uptime Monitoring**: External service (UptimeRobot, Pingdom, etc.)
- **Log Aggregation**: Centralized logging system

### Backup Requirements

- **Database**: Daily automated backups
- **Files**: Weekly backups of storage directory
- **Configuration**: Version controlled in Git
- **Retention**: Minimum 30 days, recommended 90 days

### Development vs Production

#### Development
- Can use SQLite for database
- APP_DEBUG can be true
- Can use file-based sessions
- No SSL required

#### Production
- Must use MySQL/MariaDB
- APP_DEBUG must be false
- Should use Redis for sessions/cache
- SSL/TLS required
- Proper error logging configured
- Queue workers running
- Scheduled tasks configured

---

## Installation Commands

### Ubuntu/Debian

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-xml \
    php8.2-mbstring php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl \
    php8.2-redis php8.2-opcache

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install Nginx (or Apache)
sudo apt install -y nginx
# OR
sudo apt install -y apache2 libapache2-mod-php8.2
```

### CentOS/RHEL

```bash
# Install EPEL and Remi repositories
sudo yum install -y epel-release
sudo yum install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm

# Install PHP 8.2
sudo yum install -y php82 php82-php-fpm php82-php-mysqlnd php82-php-xml \
    php82-php-mbstring php82-php-curl php82-php-zip php82-php-bcmath \
    php82-php-intl php82-php-redis php82-php-opcache

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://rpm.nodesource.com/setup_18.x | sudo bash -
sudo yum install -y nodejs

# Install MySQL
sudo yum install -y mysql-server

# Install Redis
sudo yum install -y redis
```

---

## Verification

After installation, verify all requirements:

```bash
# Check PHP version
php -v

# Check PHP extensions
php -m | grep -E "pdo|mysql|mbstring|curl|xml|zip|bcmath|redis|opcache"

# Check Composer
composer --version

# Check Node.js
node -v
npm -v

# Check MySQL
mysql --version

# Check Redis
redis-cli --version
```

---

**Last Updated**: December 2024




