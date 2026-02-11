# Amelia Booking Integration Setup Guide

This guide explains how to connect your Laravel project to the WordPress/Amelia Booking database to view and manage booking data.

## What Was Set Up

### 1. Database Connection
- Added a new database connection named `wordpress` in `config/database.php`
- This connection points to the WordPress database (`cyanboutique_flexana`) with the Amelia table prefix (`rueyn_amelia_`)

### 2. Eloquent Models
Created models for Amelia tables:
- `AmeliaAppointmentModel` - Appointments table
- `AmeliaCustomerBookingModel` - Customer bookings table
- `AmeliaServiceModel` - Services table
- `AmeliaUserModel` - Users (customers/providers) table
- `AmeliaPaymentModel` - Payments table
- `AmeliaEventModel` - Events table

All models are located in: `app/Infrastructure/Persistence/Eloquent/`

### 3. Filament Resources
Created Filament admin panel resources:
- `AmeliaBookingResource` - View and manage bookings
- `AmeliaCustomerResource` - View and manage customers
- `AmeliaServiceResource` - View and manage services

All resources are located in: `app/Filament/Resources/`

## Configuration Steps

### Step 1: Update .env File

Add these environment variables to your `.env` file:

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

**Note:** Adjust the values according to your local MySQL setup:
- If your MySQL password is not empty, set `WP_DB_PASSWORD=your_password`
- If your database name is different, update `WP_DB_DATABASE`

### Step 2: Clear Config Cache

After updating `.env`, clear the config cache:

```bash
php artisan config:clear
```

### Step 3: Access the Dashboard

1. Start your Laravel development server:
   ```bash
   php artisan serve
   ```

2. Access the Filament admin panel:
   - URL: `http://localhost:8000/admin`
   - Login with your admin credentials

3. Navigate to the "Amelia Data" section in the sidebar:
   - **Amelia Bookings** - View all customer bookings
   - **Amelia Customers** - View all customers
   - **Amelia Services** - View all services

## Features

### Amelia Bookings
- View all customer bookings with appointment details
- Filter by status (approved, pending, canceled, etc.)
- See customer information, pricing, and booking dates
- View individual booking details

### Amelia Customers
- View all customers from Amelia
- Filter by type (customer/provider) and status
- See booking count per customer
- View customer details

### Amelia Services
- View all services offered
- See pricing, duration, and capacity information
- View appointment count per service
- Filter by status

## Database Tables Used

The integration reads from these Amelia tables (with `rueyn_amelia_` prefix):
- `appointments` - Appointment records
- `customer_bookings` - Customer booking records
- `services` - Service definitions
- `users` - Customer and provider user data
- `payments` - Payment records
- `events` - Event records

## Troubleshooting

### Connection Error
If you get a database connection error:
1. Verify MySQL is running (XAMPP Control Panel)
2. Check database credentials in `.env`
3. Ensure database `cyanboutique_flexana` exists
4. Verify table prefix `rueyn_amelia_` is correct

### No Data Showing
If tables are empty:
1. Check if Amelia Booking plugin is installed and active in WordPress
2. Verify there are actual bookings in the WordPress database
3. Check table names match (with prefix `rueyn_amelia_`)

### Filament Resources Not Appearing
1. Clear cache: `php artisan optimize:clear`
2. Check if resources are registered in Filament panel
3. Verify you're logged in as an admin user

## Next Steps

You can extend this integration by:
1. Adding more Filament resources (Payments, Events, etc.)
2. Creating API endpoints to sync data
3. Adding data export functionality
4. Creating custom reports and analytics

## Notes

- This integration is **read-only** by default (viewing data only)
- To modify Amelia data, you would need to add write operations (not recommended to modify WordPress data from Laravel)
- All models use the `wordpress` database connection
- Table prefix `rueyn_amelia_` is automatically applied by the connection configuration
