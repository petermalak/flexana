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
use Illuminate\Support\Facades\URL;
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
        $appUrl = config('app.url');
        $pathFromUrl = $appUrl ? parse_url($appUrl, PHP_URL_PATH) : null;
        $pathFromUrl = ($pathFromUrl && $pathFromUrl !== '/') ? trim($pathFromUrl, '/') : '';

        // Subdirectory: prefer LIVEWIRE_BASE_PATH, else derive from APP_URL (e.g. https://sdhds.net/backend/public → backend/public)
        $basePath = trim((string) env('LIVEWIRE_BASE_PATH', $pathFromUrl), '/');
        $fullBasePath = $basePath !== '' ? '/' . $basePath : '';

        if ($fullBasePath !== '') {
            // Force Laravel to generate all URLs with this root (fixes redirects and form actions after login)
            URL::forceRootUrl(rtrim($appUrl, '/'));
            $scheme = parse_url($appUrl, PHP_URL_SCHEME);
            if ($scheme) {
                URL::forceScheme($scheme);
            }

            // Session cookie path must match the app path or the browser won't send the cookie after login
            config(['session.path' => $fullBasePath]);

            // Livewire: script and update routes must live under the same base path
            config(['livewire.asset_url' => $fullBasePath . '/livewire/livewire.js']);
            config(['livewire.base_path' => $fullBasePath]);

            \Livewire\Livewire::setUpdateRoute(function ($handle) use ($fullBasePath) {
                $routeWithBase = \Illuminate\Support\Facades\Route::post($fullBasePath . '/livewire/update', $handle)
                    ->middleware(['web']);
                \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web']);
                return $routeWithBase;
            });

            \Livewire\Livewire::setScriptRoute(function ($handle) use ($fullBasePath) {
                $routeWithBase = \Illuminate\Support\Facades\Route::get($fullBasePath . '/livewire/livewire.js', $handle);
                \Illuminate\Support\Facades\Route::get('/livewire/livewire.js', $handle);
                return $routeWithBase;
            });
        } else {
            \Livewire\Livewire::setUpdateRoute(function ($handle) {
                return \Illuminate\Support\Facades\Route::post('/livewire/update', $handle)
                    ->middleware(['web']);
            });
        }

        // Add custom CSS to make white logo visible
        \Filament\Support\Facades\FilamentView::registerRenderHook(
            'panels::head.start',
            fn (): string => '<link rel="stylesheet" href="' . asset('css/logo-styles.css') . '">'
        );
    }
}
