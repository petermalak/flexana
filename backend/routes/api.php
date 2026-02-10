<?php

use App\Interfaces\Http\Controllers\Api\BookingController;
use App\Interfaces\Http\Controllers\Api\ClassTypeController;
use App\Interfaces\Http\Controllers\Api\CustomerController;
use App\Interfaces\Http\Controllers\Api\EventController;
use App\Interfaces\Http\Controllers\Api\EventInstanceController;
use App\Interfaces\Http\Controllers\Api\Mobile\BannerController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobileAuthController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobileBookingController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobileInstructorController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobilePackageController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobileServiceController;
use App\Interfaces\Http\Controllers\Api\Mobile\MobileSessionController;
use App\Interfaces\Http\Controllers\Api\PackageController;
use App\Interfaces\Http\Controllers\Api\ServiceController;
use App\Interfaces\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

// Admin / Frontend API (signature auth)
Route::prefix('v1')
    ->middleware(['api.signature'])
    ->group(function (): void {
        Route::get('events', [EventController::class, 'index']);
        Route::post('events', [EventController::class, 'store']);
        Route::get('events/{event}', [EventController::class, 'show']);
        Route::put('events/{event}', [EventController::class, 'update']);
        Route::delete('events/{event}', [EventController::class, 'destroy']);

        Route::get('event-instances', [EventInstanceController::class, 'index']);
        Route::post('event-instances', [EventInstanceController::class, 'store']);
        Route::get('event-instances/{instance}', [EventInstanceController::class, 'show']);
        Route::put('event-instances/{instance}', [EventInstanceController::class, 'update']);
        Route::delete('event-instances/{instance}', [EventInstanceController::class, 'destroy']);

        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);

        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::get('staff/{staff}', [StaffController::class, 'show']);
        Route::put('staff/{staff}', [StaffController::class, 'update']);
        Route::delete('staff/{staff}', [StaffController::class, 'destroy']);

        Route::get('services', [ServiceController::class, 'index']);
        Route::post('services', [ServiceController::class, 'store']);
        Route::get('services/{service}', [ServiceController::class, 'show']);
        Route::put('services/{service}', [ServiceController::class, 'update']);
        Route::delete('services/{service}', [ServiceController::class, 'destroy']);

        Route::get('class-types', [ClassTypeController::class, 'index']);
        Route::post('class-types', [ClassTypeController::class, 'store']);
        Route::get('class-types/{classType}', [ClassTypeController::class, 'show']);
        Route::put('class-types/{classType}', [ClassTypeController::class, 'update']);
        Route::delete('class-types/{classType}', [ClassTypeController::class, 'destroy']);

        Route::get('packages', [PackageController::class, 'index']);
        Route::post('packages', [PackageController::class, 'store']);
        Route::get('packages/{package}', [PackageController::class, 'show']);
        Route::put('packages/{package}', [PackageController::class, 'update']);
        Route::delete('packages/{package}', [PackageController::class, 'destroy']);

        Route::post('users/customers', [CustomerController::class, 'storeFlutter']);
    });

// Mobile / App API under /api/v1 — all data endpoints require Bearer token
Route::prefix('v1')
    ->group(function (): void {
        // Auth (public)
        Route::post('auth/login', [MobileAuthController::class, 'login']);
        Route::post('auth/signup', [MobileAuthController::class, 'signup']);
        Route::post('auth/forgot-password', [MobileAuthController::class, 'forgotPassword']);
        Route::post('auth/reset-password', [MobileAuthController::class, 'resetPassword']);
        // Backend OTP: verify code sent via SMS (Twilio/log)
        Route::post('auth/verify', [MobileAuthController::class, 'verify']);

        // All mobile app endpoints (authenticated)
        Route::middleware(['auth:sanctum:api'])->group(function (): void {
            // Home screen
            Route::get('banners', [BannerController::class, 'index']);
            Route::get('instructors', [MobileInstructorController::class, 'index']);
            Route::get('service', [MobileServiceController::class, 'index']);
            // Schedule screen
            Route::get('instructors/simple', [MobileInstructorController::class, 'simple']);
            Route::get('service/simple', [MobileServiceController::class, 'simple']);
            Route::get('sessions', [MobileSessionController::class, 'index']);
            Route::post('session-bookings', [MobileBookingController::class, 'store']);
            Route::post('cancel-booking', [MobileBookingController::class, 'cancel']);
            // Packages screen (path 'package-offers' to avoid conflict with admin GET /v1/packages)
            Route::get('package-offers', [MobilePackageController::class, 'index']);
            Route::post('purchase-package', [MobilePackageController::class, 'purchase']);
            // Profile & logout
            Route::post('auth/logout', [MobileAuthController::class, 'logout']);
            Route::get('auth/me', [MobileAuthController::class, 'me']);
            Route::put('auth/me', [MobileAuthController::class, 'updateMe']);
            Route::post('auth/send-phone-change-code', [MobileAuthController::class, 'sendPhoneChangeCode']);
            Route::post('auth/change-password', [MobileAuthController::class, 'changePassword']);
            Route::post('auth/fcm-token', [MobileAuthController::class, 'registerFcmToken']);
            Route::delete('auth/delete-account', [MobileAuthController::class, 'deleteAccount']);
            Route::get('appointments/history', [MobileBookingController::class, 'history']);
        });
    });
