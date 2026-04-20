<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

// Serve files from storage/app/public (works with or without subdirectory in URL)
$serveStorage = function (string $path) {
    if (!Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return response()->file(Storage::disk('public')->path($path), [
        'Cache-Control' => 'public, max-age=31536000',
    ]);
};
Route::get('/storage/{path}', $serveStorage)->where('path', '.*')->name('storage.serve');
// Subdirectory URL (e.g. when APP_URL is https://sdhds.net/backend/backend/public)
$storagePrefix = trim((string) parse_url(config('app.url'), PHP_URL_PATH), '/');
if ($storagePrefix !== '') {
    Route::get($storagePrefix . '/storage/{path}', $serveStorage)->where('path', '.*')->name('storage.serve.prefixed');
}

// Include authentication routes
require __DIR__.'/auth.php';

// Public embeddable booking UI for the WordPress website (iframe-friendly).
Route::middleware(['booking.embed'])->group(function (): void {
    Route::view('/web-session-bookings', 'booking.web_session_bookings')
        ->name('booking.web_session_bookings');
});

// Paymob return URL (user redirect) – must be web (not /api) so Paymob can redirect a browser.
Route::get('/paymob/return', [\App\Interfaces\Http\Controllers\Api\WebSessionPaymobController::class, 'return'])
    ->name('paymob.return');

// Break out of iframe and redirect to WordPress page.
Route::get('/paymob/finish', [\App\Interfaces\Http\Controllers\Api\WebSessionPaymobController::class, 'finish'])
    ->name('paymob.finish');

// Paymob server-to-server callback (transaction processed).
Route::post('/paymob/callback', [\App\Interfaces\Http\Controllers\Api\WebSessionPaymobController::class, 'callback'])
    ->name('paymob.callback');

// Dashboard route (protected by auth middleware)
// Redirects to Filament admin panel since Inertia Dashboard component doesn't exist
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/admin');
    })->name('dashboard');

    // Appointments resource lives at /admin/appointments; redirect old URL
    Route::redirect('/admin/amelia-appointments', '/admin/appointments', 301)
        ->name('admin.amelia-appointments.redirect');
    Route::redirect('/admin/amelia-appointments/create', '/admin/appointments/create', 301);
    Route::get('/admin/amelia-appointments/{record}/edit', function ($record) {
        return redirect("/admin/appointments/{$record}/edit", 301);
    })->name('admin.amelia-appointments.edit.redirect');
});

// Test route to check authentication status (remove after debugging)
Route::get('/test-auth', function () {
    return response()->json([
        'authenticated' => Auth::check(),
        'user' => Auth::check() ? [
            'id' => Auth::id(),
            'email' => Auth::user()->email,
            'name' => Auth::user()->name,
        ] : null,
        'guard' => Auth::getDefaultDriver(),
    ]);
});

// Root route - redirect to dashboard (which redirects to Filament admin) if authenticated, otherwise to login
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
