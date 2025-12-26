<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Include authentication routes
require __DIR__.'/auth.php';

// Dashboard route (protected by auth middleware)
// Redirects to Filament admin panel since Inertia Dashboard component doesn't exist
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect('/admin');
    })->name('dashboard');
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
