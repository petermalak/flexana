<?php

use App\Interfaces\Http\Controllers\Api\BookingController;
use App\Interfaces\Http\Controllers\Api\ClassTypeController;
use App\Interfaces\Http\Controllers\Api\CustomerController;
use App\Interfaces\Http\Controllers\Api\EventController;
use App\Interfaces\Http\Controllers\Api\EventInstanceController;
use App\Interfaces\Http\Controllers\Api\PackageController;
use App\Interfaces\Http\Controllers\Api\ServiceController;
use App\Interfaces\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api.signature']) // Use API signature authentication for frontend
    ->group(function (): void {
        // Events
        Route::get('events', [EventController::class, 'index']);
        Route::post('events', [EventController::class, 'store']);
        Route::get('events/{event}', [EventController::class, 'show']);
        Route::put('events/{event}', [EventController::class, 'update']);
        Route::delete('events/{event}', [EventController::class, 'destroy']);

        // Event Instances
        Route::get('event-instances', [EventInstanceController::class, 'index']);
        Route::post('event-instances', [EventInstanceController::class, 'store']);
        Route::get('event-instances/{instance}', [EventInstanceController::class, 'show']);
        Route::put('event-instances/{instance}', [EventInstanceController::class, 'update']);
        Route::delete('event-instances/{instance}', [EventInstanceController::class, 'destroy']);

        // Bookings
        Route::get('bookings', [BookingController::class, 'index']);
        Route::post('bookings', [BookingController::class, 'store']); // Handles both formats

        // Customers
        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);
        
        // Staff/Instructors
        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::get('staff/{staff}', [StaffController::class, 'show']);
        Route::put('staff/{staff}', [StaffController::class, 'update']);
        Route::delete('staff/{staff}', [StaffController::class, 'destroy']);

        // Services
        Route::get('services', [ServiceController::class, 'index']);
        Route::post('services', [ServiceController::class, 'store']); // Handles Flutter format
        Route::get('services/{service}', [ServiceController::class, 'show']);
        Route::put('services/{service}', [ServiceController::class, 'update']);
        Route::delete('services/{service}', [ServiceController::class, 'destroy']);

        // Class Types
        Route::get('class-types', [ClassTypeController::class, 'index']);
        Route::post('class-types', [ClassTypeController::class, 'store']);
        Route::get('class-types/{classType}', [ClassTypeController::class, 'show']);
        Route::put('class-types/{classType}', [ClassTypeController::class, 'update']);
        Route::delete('class-types/{classType}', [ClassTypeController::class, 'destroy']);

        // Packages
        Route::get('packages', [PackageController::class, 'index']);
        Route::post('packages', [PackageController::class, 'store']);
        Route::get('packages/{package}', [PackageController::class, 'show']);
        Route::put('packages/{package}', [PackageController::class, 'update']);
        Route::delete('packages/{package}', [PackageController::class, 'destroy']);
        
        // Flutter-specific routes
        Route::post('users/customers', [CustomerController::class, 'storeFlutter']);
    });

