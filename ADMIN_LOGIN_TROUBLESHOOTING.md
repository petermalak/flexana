# Admin login not working (e.g. https://sdhds.net/backend/backend/public/admin/login)

If you **cannot log in** to the Flexana admin panel even with the correct credentials, check the following.

---

## 1. Use admin users, not app customers

The admin panel uses the **User** model (`users` table), **not** the mobile app **Customer** model.

- **Admin login:** email + password from the **users** table.
- **Mobile app:** phone + password from the **customers** table.

If you only have customer accounts, create an admin user (see below).

---

## 2. Create or reset an admin user

From the project root on the server:

```bash
# Create default admin (email: admin@admin.com, password: 123456789)
php artisan db:seed --class=AdminUserSeeder
```

To set a new password for an existing admin:

```bash
php artisan tinker
>>> $u = App\Models\User::where('email', 'admin@admin.com')->first();
>>> $u->password = bcrypt('your-new-password');
>>> $u->save();
>>> exit
```

---

## 3. Session cookie path (subdirectory deployments)

When the app is in a subdirectory (e.g. `https://sdhds.net/backend/backend/public`), the session cookie must be set for that path or the browser will not send it after login, so you appear logged out.

**Option A – Set APP_URL (recommended)**  
If `APP_URL` is set correctly, the app will set the session path automatically:

```env
APP_URL=https://sdhds.net/backend/backend/public
```

**Option B – Set SESSION_PATH explicitly**

```env
SESSION_PATH=/backend/backend/public
```

Restart PHP / clear config cache after changing `.env`:

```bash
php artisan config:clear
# If you use opcache or run behind a web server, restart PHP or the server if needed.
```

---

## 4. Other checks

- **HTTPS:** If the site is served over HTTPS, ensure the server is trusted (e.g. `TrustProxies` middleware) so Laravel sets `secure` on cookies correctly.
- **Cache:** Run `php artisan config:clear` and `php artisan cache:clear` after any `.env` or config change.
- **Credentials:** Default seeder admin is `admin@admin.com` / `123456789`. Change the password in production.

---

## Quick checklist

| Check | Action |
|-------|--------|
| Logging in with **email** (not phone) | Admin uses email from `users` table |
| Admin user exists | Run `php artisan db:seed --class=AdminUserSeeder` |
| Subdirectory URL | Set `APP_URL=https://sdhds.net/backend/backend/public` or `SESSION_PATH=/backend/backend/public` |
| After .env change | Run `php artisan config:clear` |
