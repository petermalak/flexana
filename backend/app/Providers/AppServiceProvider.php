<?php

namespace App\Providers;

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
        Vite::prefetch(concurrency: 3);

        // Add custom CSS to make white logo visible
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            'panels::head.start',
            fn (): string => '<link rel="stylesheet" href="' . asset('css/logo-styles.css') . '">'
        );

        // Ensure Livewire routes are registered (required for Filament)
        // Configure Livewire to use the correct base path for subdirectory deployments
        \Livewire\Livewire::setUpdateRoute(function ($handle) {
            // Get base path from environment variable or APP_URL
            // For production: set LIVEWIRE_BASE_PATH=/backend/backend/public in .env
            $basePath = env('LIVEWIRE_BASE_PATH', '');
            
            if ($basePath) {
                // Remove leading/trailing slashes and add the livewire update path
                $basePath = trim($basePath, '/');
                $updatePath = '/' . $basePath . '/livewire/update';
            } else {
                $updatePath = '/livewire/update';
            }
            
            return \Illuminate\Support\Facades\Route::post($updatePath, $handle)
                ->middleware(['web']);
        });
    }
}
