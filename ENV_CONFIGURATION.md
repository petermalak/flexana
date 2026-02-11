# .env Configuration for Mobile API

Add the following environment variables to your `.env` file in the Laravel backend project.

## WordPress/Amelia Database Connection

These variables configure the connection to your WordPress database where Amelia Booking data is stored:

```env
# WordPress/Amelia Database Connection
WP_DB_HOST=127.0.0.1
WP_DB_PORT=3306
WP_DB_DATABASE=cyanboutique_flexana
WP_DB_USERNAME=root
WP_DB_PASSWORD=
WP_DB_CHARSET=utf8mb4
WP_DB_COLLATION=utf8mb4_unicode_ci
```

### Configuration Details:

- **WP_DB_HOST**: Database host (use `127.0.0.1` for local, `localhost` may cause issues)
- **WP_DB_PORT**: MySQL port (default: `3306`)
- **WP_DB_DATABASE**: Your WordPress database name (from `wp-config.php` → `DB_NAME`)
- **WP_DB_USERNAME**: MySQL username (from `wp-config.php` → `DB_USER`)
- **WP_DB_PASSWORD**: MySQL password (from `wp-config.php` → `DB_PASSWORD`)
- **WP_DB_CHARSET**: Character set (default: `utf8mb4`)
- **WP_DB_COLLATION**: Collation (default: `utf8mb4_unicode_ci`)

**Note**: The table prefix `rueyn_amelia_` is hardcoded in `config/database.php` and matches your WordPress setup.

## Amelia Write Operations

Enable write operations to allow the mobile API to create/update bookings and packages:

```env
# Enable Amelia write operations (set to true to allow CRUD operations)
AMELIA_ENABLE_WRITE=true
```

**Warning**: When set to `true`, the API can modify Amelia Booking tables. Only enable this if you're not using the WordPress Amelia plugin simultaneously, or if you understand the risks.

## Complete .env Example

Here's a complete example of the relevant sections for your `.env` file:

```env
# Application
APP_NAME="Flexana Backend"
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Default Database (Laravel's own database)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_laravel_database
DB_USERNAME=root
DB_PASSWORD=

# WordPress/Amelia Database Connection (for Mobile API)
WP_DB_HOST=127.0.0.1
WP_DB_PORT=3306
WP_DB_DATABASE=cyanboutique_flexana
WP_DB_USERNAME=root
WP_DB_PASSWORD=
WP_DB_CHARSET=utf8mb4
WP_DB_COLLATION=utf8mb4_unicode_ci

# Amelia Configuration
AMELIA_ENABLE_WRITE=true

# Other Laravel configurations...
BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

## Verification

After adding these variables, verify the connection by:

1. **Clear config cache**:
   ```bash
   php artisan config:clear
   ```

2. **Test the connection** (optional - create a test route):
   ```php
   Route::get('/test-wp-connection', function() {
       try {
           $users = DB::connection('wordpress')->table('rueyn_amelia_users')->count();
           return response()->json(['success' => true, 'users_count' => $users]);
       } catch (\Exception $e) {
           return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
       }
   });
   ```

3. **Test mobile API endpoints** using the Postman collection.

## Production Configuration

For production, update these values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

WP_DB_HOST=your_production_db_host
WP_DB_PORT=3306
WP_DB_DATABASE=your_production_wp_database
WP_DB_USERNAME=your_production_db_user
WP_DB_PASSWORD=your_secure_password
```

## Troubleshooting

### Connection Refused
- Check if MySQL is running
- Verify `WP_DB_HOST` is correct (`127.0.0.1` vs `localhost`)
- Check firewall settings

### Access Denied
- Verify database credentials match your WordPress `wp-config.php`
- Check MySQL user permissions

### Table Not Found
- Verify the database name is correct
- Check that Amelia tables exist with prefix `rueyn_amelia_`
- Run: `SHOW TABLES LIKE 'rueyn_amelia_%'` in MySQL

### Empty Results
- Check that Amelia data exists in the database
- Verify table prefix matches your WordPress setup
- Check service/instructor status (should be `visible`)
