# Create Admin User on Production

## Quick Fix (Run Now on Production)

Since the `uuid` field is required, you can create the admin user manually using one of these methods:

### Option 1: Using Artisan Tinker (Recommended)

```bash
php artisan tinker
```

Then run:

```php
use App\Models\User;
use Illuminate\Support\Str;

User::create([
    'uuid' => Str::uuid()->toString(),
    'name' => 'admin',
    'email' => 'admin@admin.com',
    'password' => bcrypt('your-secure-password-here'),
    'email_verified_at' => now(),
]);
```

### Option 2: Using SQL Directly

```bash
php artisan tinker --execute="
use App\Models\User;
use Illuminate\Support\Str;
User::create([
    'uuid' => Str::uuid()->toString(),
    'name' => 'admin',
    'email' => 'admin@admin.com',
    'password' => bcrypt('your-secure-password-here'),
    'email_verified_at' => now(),
]);
echo 'Admin user created successfully!';
"
```

### Option 3: Direct SQL (if tinker doesn't work)

```sql
INSERT INTO users (uuid, name, email, password, email_verified_at, created_at, updated_at)
VALUES (
    (SELECT lower(hex(randomblob(4)) || '-' || hex(randomblob(2)) || '-4' || substr(hex(randomblob(2)), 2) || '-' || substr('89ab', abs(random()) % 4 + 1, 1) || substr(hex(randomblob(2)), 2) || '-' || hex(randomblob(6)))),
    'admin',
    'admin@admin.com',
    '$2y$12$SyUvDR.JG/mbT7eM/vS4Du5fti2xQWvUYKxBBEDMGILrGxdtcxQFe', -- Replace with your bcrypt hash
    datetime('now'),
    datetime('now'),
    datetime('now')
);
```

**Note:** For SQL option, generate a password hash first:
```bash
php artisan tinker --execute="echo bcrypt('your-password');"
```

---

## Permanent Fix (After Code Update)

The `User` model has been updated to **auto-generate `uuid`** when creating users. After you deploy this change, `php artisan make:filament-user` will work normally.

The fix adds a `booted()` method that automatically generates a UUID if one isn't provided:

```php
protected static function booted(): void
{
    static::creating(function (self $user): void {
        if (empty($user->uuid)) {
            $user->uuid = Str::uuid()->toString();
        }
    });
}
```

---

## Verify User Creation

After creating the user, verify it works:

```bash
php artisan tinker --execute="echo App\Models\User::where('email', 'admin@admin.com')->exists() ? 'User exists!' : 'User not found';"
```

Then try logging into `/admin` with:
- Email: `admin@admin.com`
- Password: (the password you set)
