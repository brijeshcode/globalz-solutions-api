<?php

use App\Http\Controllers\Api\Landlord\TenantManagementController;
use App\Http\Controllers\Api\Landlord\FeatureController;
use Illuminate\Support\Facades\Route;

/**
 * Landlord Management Routes
 *
 * These routes are for landlord-level operations (managing tenants)
 * NO tenant middleware should be applied here
 * These routes use the landlord database connection
 */


// Landlord routes (authentication required, NO tenant required)
Route::middleware('auth:sanctum')->group(function () {

    // Tenant Management
    Route::prefix('tenants')->name('tenants.')->group(function () {

        // Statistics
        Route::get('stats', [TenantManagementController::class, 'stats'])->name('stats');

        // List all tenants
        Route::get('/', [TenantManagementController::class, 'index'])->name('index');

        // Create new tenant
        Route::post('/', [TenantManagementController::class, 'store'])->name('store');

        // Get specific tenant
        Route::get('{tenant}', [TenantManagementController::class, 'show'])->name('show');

        // Update tenant
        Route::put('{tenant}', [TenantManagementController::class, 'update'])->name('update');

        // Deactivate tenant
        Route::delete('{tenant}', [TenantManagementController::class, 'destroy'])->name('destroy');

        // Activate tenant
        Route::patch('{tenant}/activate', [TenantManagementController::class, 'activate'])->name('activate');

        // Run migrations for tenant
        Route::post('{tenant}/migrations', [TenantManagementController::class, 'runMigrations'])->name('runMigrations');

        // Get tenant features
        Route::get('{tenant}/features', [FeatureController::class, 'getTenantFeatures'])->name('features.index');

        // Assign/update feature to tenant
        Route::post('{tenant}/features/{feature}', [FeatureController::class, 'assignFeatureToTenant'])->name('features.assign');

        // Bulk update tenant features
        Route::post('{tenant}/features', [FeatureController::class, 'bulkUpdateTenantFeatures'])->name('features.bulk-update');
    });

    // Feature Management
    Route::prefix('features')->name('features.')->group(function () {

        // List all features
        Route::get('/', [FeatureController::class, 'index'])->name('index');

        // Create new feature
        Route::post('/', [FeatureController::class, 'store'])->name('store');

        // Update feature
        Route::put('{feature}', [FeatureController::class, 'update'])->name('update');

        // Delete feature
        Route::delete('{feature}', [FeatureController::class, 'destroy'])->name('destroy');
    });
});
