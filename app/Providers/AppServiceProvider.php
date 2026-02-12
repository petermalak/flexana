<?php

namespace App\Providers;

use App\Application\Auth\SmsVerificationServiceInterface;
use App\Application\Auth\SmsVerificationService;
use App\Domain\Bookings\BookingRepositoryInterface;
use App\Domain\ClassTypes\ClassTypeRepositoryInterface;
use App\Domain\Customers\CustomerRepositoryInterface;
use App\Domain\Events\EventInstanceRepositoryInterface;
use App\Domain\Events\EventRepositoryInterface;
use App\Domain\Packages\PackageRepositoryInterface;
use App\Domain\Services\ServiceRepositoryInterface;
use App\Domain\Staff\StaffRepositoryInterface;
use App\Infrastructure\Auth\FirebaseTokenVerifier;
use App\Infrastructure\Persistence\Repositories\BookingRepository;
use App\Infrastructure\Persistence\Repositories\ClassTypeRepository;
use App\Infrastructure\Persistence\Repositories\CustomerRepository;
use App\Infrastructure\Persistence\Repositories\EventInstanceRepository;
use App\Infrastructure\Persistence\Repositories\EventRepository;
use App\Infrastructure\Persistence\Repositories\PackageRepository;
use App\Infrastructure\Persistence\Repositories\ServiceRepository;
use App\Infrastructure\Persistence\Repositories\StaffRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(EventInstanceRepositoryInterface::class, EventInstanceRepository::class);
        $this->app->bind(BookingRepositoryInterface::class, BookingRepository::class);
        $this->app->bind(CustomerRepositoryInterface::class, CustomerRepository::class);
        $this->app->bind(StaffRepositoryInterface::class, StaffRepository::class);
        $this->app->bind(ServiceRepositoryInterface::class, ServiceRepository::class);
        $this->app->bind(ClassTypeRepositoryInterface::class, ClassTypeRepository::class);
        $this->app->bind(PackageRepositoryInterface::class, PackageRepository::class);
        $this->app->bind(SmsVerificationServiceInterface::class, SmsVerificationService::class);

        $this->app->singleton(FirebaseTokenVerifier::class, function ($app) {
            return new FirebaseTokenVerifier(
                cache: $app->make('cache.store'),
                http: $app->make(HttpFactory::class),
                projectId: (string) config('firebase.project_id'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Only enable Vite prefetch if manifest exists (avoids error in production when assets aren't built)
        if (file_exists(public_path('build/manifest.json'))) {
            Vite::prefetch(concurrency: 3);
        }

        // Add custom CSS to make white logo visible
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            'panels::head.start',
            fn (): string => '<link rel="stylesheet" href="' . asset('css/logo-styles.css') . '">'
        );

        // Ensure Livewire routes are registered (required for Filament)
        // Configure Livewire to use the correct base path for subdirectory deployments
        $basePath = env('LIVEWIRE_BASE_PATH', '');

        if ($basePath) {
            // Remove leading/trailing slashes
            $basePath = trim($basePath, '/');
            $fullBasePath = '/' . $basePath;

            // Set the update route with base path and also register at root level
            \Livewire\Livewire::setUpdateRoute(function ($handle) use ($fullBasePath) {
                // Register the route with base path
                $routeWithBase = \Illuminate\Support\Facades\Route::post($fullBasePath . '/livewire/update', $handle)
                    ->middleware(['web']);

                // Also register at root level to catch /livewire/update requests
                \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web']);

                return $routeWithBase;
            });

            // Set the script route with base path (for Livewire assets)
            \Livewire\Livewire::setScriptRoute(function ($handle) use ($fullBasePath) {
                // Register both routes
                $routeWithBase = \Illuminate\Support\Facades\Route::get($fullBasePath . '/livewire/livewire.js', $handle);
                \Illuminate\Support\Facades\Route::get('/livewire/livewire.js', $handle);
                return $routeWithBase;
            });
        } else {
            // Default configuration for local development
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web']);
            });
        }
    }
}
