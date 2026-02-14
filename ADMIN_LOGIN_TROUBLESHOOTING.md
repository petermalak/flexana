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

## 3. Subdirectory deployment (session + Livewire 404 + redirect after login)

When the app is in a subdirectory (e.g. `https://sdhds.net/backend/public`), you **must** set `APP_URL` to the **full URL including the path**. The app uses this for:

- **Session cookie path** – so the browser sends the cookie after login (no redirect back to login).
- **Livewire script URL** – so `/livewire/livewire.js` is loaded from the correct path (no 404).
- **Redirects and form actions** – so login and other URLs point to the app.

Set in `.env`:

```env
APP_URL=https://sdhds.net/backend/public
```

Use your real path (e.g. `backend/public`, `backend/backend/public`, or whatever the document root of the app is). No trailing slash.

Optional: if you need a different base path for Livewire only, set:

```env
LIVEWIRE_BASE_PATH=backend/public
```

After changing `.env`:

```bash
php artisan config:clear
# If you use config:cache in production, run: php artisan config:cache
# Restart PHP or the web server if needed (e.g. opcache).
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
| Subdirectory URL | Set `APP_URL=https://sdhds.net/backend/public` (full URL including path) |
| After .env change | Run `php artisan config:clear` |
