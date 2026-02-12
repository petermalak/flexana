# Fix Vite Manifest Not Found Error

## Problem
```
ViteManifestNotFoundException
Vite manifest not found at: /home/.../public/build/manifest.json
```

This happens because Vite assets haven't been built for production.

## Solutions

### Option 1: Build Assets Locally and Deploy (RECOMMENDED)

If you need the Filament CSS or Inertia.js frontend:

**On your local machine:**

```bash
# Install dependencies (if not already done)
npm install

# Build for production
npm run build

# This creates: public/build/manifest.json and compiled assets
```

**Then deploy:**
- Upload the `public/build/` folder to your server
- Make sure it's at: `/home/.../backend/backend/public/build/`

### Option 2: Disable Vite Manifest Check in Production (QUICK FIX)

If you don't need Vite assets (Filament works without them), configure Laravel to skip the check:

**Add to `config/vite.php` (create if doesn't exist):**

```php
<?php

return [
    'build_path' => env('VITE_BUILD_PATH', 'build'),
    
    // Skip manifest check in production if file doesn't exist
    'ignore_missing_manifest' => env('APP_ENV') === 'production',
];
```

**Or modify `app/Providers/AppServiceProvider.php`:**

```php
use Illuminate\Foundation\Vite;

public function boot(): void
{
    // Only enable Vite prefetch if manifest exists
    if (file_exists(public_path('build/manifest.json'))) {
        Vite::prefetch(concurrency: 3);
    }
    
    // ... rest of your boot method
}
```

### Option 3: Create Empty Manifest (TEMPORARY)

**On server, create empty manifest:**

```bash
mkdir -p /home/u354738377/domains/sdhds.net/public_html/backend/backend/public/build
echo '{}' > /home/u354738377/domains/sdhds.net/public_html/backend/backend/public/build/manifest.json
```

This stops the error but won't load any Vite assets.

### Option 4: Remove Vite References (If Not Needed)

If you're only using Filament admin panel and don't need Inertia.js:

1. Remove `@vite` from `resources/views/app.blade.php` (if that view is used)
2. Remove `Vite::prefetch()` from `AppServiceProvider.php`
3. Filament will use its own pre-compiled assets

## Recommended Approach

**For Filament-only admin panel:**
- Use Option 2 (disable manifest check) - Filament doesn't need Vite
- Or Option 4 (remove Vite references)

**For full-stack app with Inertia.js:**
- Use Option 1 (build and deploy assets)

## Quick Server Fix (Right Now)

Run this on your server to stop the error temporarily:

```bash
cd /home/u354738377/domains/sdhds.net/public_html/backend/backend/public
mkdir -p build
echo '{}' > build/manifest.json
chmod 644 build/manifest.json
```

Then implement Option 2 or Option 4 for a permanent solution.
