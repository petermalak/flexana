# Quick Sync Reference

## 🚀 Quick Start

### Sync ALL WordPress data to MySQL:
```bash
php artisan amelia:sync-to-mysql
```

### Preview what will be synced (dry run):
```bash
php artisan amelia:sync-to-mysql --dry-run
```

---

## 📋 What Gets Synced

✅ **Customers** - `rueyn_amelia_users` (type='customer') → `customers`  
✅ **Staff** - `rueyn_amelia_users` (type='provider/manager/admin') → `staff`  
✅ **Services** - `rueyn_amelia_services` → `services`  
✅ **Packages** - `rueyn_amelia_packages` → `packages`  
✅ **Appointments** - `rueyn_amelia_appointments` → `appointments`  
✅ **Bookings** - `rueyn_amelia_customer_bookings` → `bookings`  
✅ **Payments** - `rueyn_amelia_payments` → `payments`  
✅ **Relationships** - Service-staff and package-service links

---

## 🔄 Auto-Sync

**Automatic cloning happens when:**
- `AmeliaCustomerResolver` reads customers from WordPress → Auto-clones to MySQL
- Future: Any code reading WordPress data will auto-clone

---

## 📝 Commands

| Command | Description |
|---------|-------------|
| `php artisan amelia:sync-to-mysql` | Sync everything from WordPress to MySQL |
| `php artisan amelia:sync-to-mysql --dry-run` | Preview sync without changes |
| `php artisan amelia:sync-to-mysql --skip-duplicates` | Skip existing records |
| `php artisan amelia:sync-to-mysql --only=customers,bookings` | Sync specific entities |
| `php artisan amelia:import` | Import command (enhanced with auto-sync) |

---

## ✅ Verification

After syncing, verify data in MySQL:

```bash
php artisan tinker
```

```php
\App\Models\Customer::count();
\App\Infrastructure\Persistence\Eloquent\ServiceModel::count();
\App\Infrastructure\Persistence\Eloquent\BookingModel::count();
```

---

## 🎯 Your Database Setup

**Primary Database (MySQL):**
- Connection: `mysql`
- Database: `flexana` (from `.env` `DB_DATABASE`)
- **All Laravel models use this by default**

**WordPress Database (Temporary):**
- Connection: `wordpress`
- Database: `cyanboutique_flexana` (from `.env` `WP_DB_DATABASE`)
- **Used only for reading/migrating before deletion**

---

## 📚 Full Documentation

See `WORDPRESS_TO_MYSQL_SYNC_GUIDE.md` for complete guide.
