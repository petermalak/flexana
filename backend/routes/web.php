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

// Livewire update route proxy for subdirectory deployments
// This catches requests to /livewire/update and proxies them to the correct path
if (env('LIVEWIRE_BASE_PATH')) {
    $basePath = '/' . trim(env('LIVEWIRE_BASE_PATH'), '/');
    Route::post('/livewire/update', function () use ($basePath) {
        // Create a new request to the correct endpoint
        $request = request();
        $correctPath = $basePath . '/livewire/update';

        // Create internal request to the correct route
        $internalRequest = \Illuminate\Http\Request::create($correctPath, 'POST', $request->all(), $request->cookies->all(), $request->files->all(), $request->server->all(), $request->getContent());
        $internalRequest->headers->replace($request->headers->all());

        // Handle the request using the correct route
        return app()->handle($internalRequest);
    })->middleware(['web']);
}

// Root route - redirect to dashboard (which redirects to Filament admin) if authenticated, otherwise to login
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});
