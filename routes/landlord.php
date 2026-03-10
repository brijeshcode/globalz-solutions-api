<?php

use App\Http\Controllers\Api\Landlord\FeatureBundleController;
use App\Http\Controllers\Api\Landlord\FeatureController;
use App\Http\Controllers\Api\Landlord\TenantCacheController;
use App\Http\Controllers\Api\Landlord\TenantCurrencyController;
use App\Http\Controllers\Api\Landlord\TenantManagementController;
use App\Http\Controllers\Api\Landlord\TenantMigrationController;
use App\Http\Controllers\Api\Landlord\TenantSettingsController;
use App\Http\Controllers\Api\Landlord\TenantSetupController;
use App\Http\Controllers\Api\Landlord\TenantUserController;
use Illuminate\Support\Facades\Route;

/**
 * Landlord Management Routes
 *
 * No tenant middleware here — these routes operate on the landlord DB
 * or explicitly switch into a tenant context via $tenant->execute().
 */

Route::middleware('auth:sanctum')->group(function () {

    // ── Landlord migrations ────────────────────────────────────────────────
    Route::post('/migrations', [TenantMigrationController::class, 'runLandlordMigrations'])
        ->name('landlord.migrations.run');

    // ── Tenant management ──────────────────────────────────────────────────
    Route::prefix('tenants')->name('tenants.')->group(function () {

        // Stats
        Route::get('stats', [TenantManagementController::class, 'stats'])->name('stats');

        // CRUD
        Route::get('/', [TenantManagementController::class, 'index'])->name('index');
        Route::post('/', [TenantManagementController::class, 'store'])->name('store');
        Route::get('{tenant}', [TenantManagementController::class, 'show'])->name('show');
        Route::put('{tenant}', [TenantManagementController::class, 'update'])->name('update');
        Route::delete('{tenant}', [TenantManagementController::class, 'destroy'])->name('destroy');
        Route::patch('{tenant}/activate', [TenantManagementController::class, 'activate'])->name('activate');

        // ── Setup & readiness ──────────────────────────────────────────────
        Route::post('{tenant}/setup', [TenantSetupController::class, 'setup'])->name('setup');
        Route::get('{tenant}/readiness', [TenantSetupController::class, 'readiness'])->name('readiness');

        // ── Settings ───────────────────────────────────────────────────────
        Route::get('{tenant}/settings', [TenantSettingsController::class, 'show'])->name('settings.show');
        Route::patch('{tenant}/settings', [TenantSettingsController::class, 'update'])->name('settings.update');

        // ── Migrations ─────────────────────────────────────────────────────
        Route::post('migrations/run-all', [TenantMigrationController::class, 'runAllTenantsMigrations'])
            ->name('migrations.runAll');
        Route::post('{tenant}/migrations', [TenantMigrationController::class, 'runTenantMigrations'])
            ->name('migrations.run');

        // ── Cache ──────────────────────────────────────────────────────────
        Route::post('{tenant}/cache/invalidate', [TenantCacheController::class, 'invalidate'])->name('cache.invalidate');

        // ── Currencies ─────────────────────────────────────────────────────
        Route::get('{tenant}/currencies', [TenantCurrencyController::class, 'index'])->name('currencies.index');
        Route::post('{tenant}/currencies', [TenantCurrencyController::class, 'store'])->name('currencies.store');
        Route::put('{tenant}/currencies/{code}', [TenantCurrencyController::class, 'update'])->name('currencies.update');

        // ── Users ──────────────────────────────────────────────────────────
        Route::get('{tenant}/users', [TenantUserController::class, 'index'])->name('users.index');
        Route::post('{tenant}/users', [TenantUserController::class, 'store'])->name('users.store');

        // ── Features (individual) ──────────────────────────────────────────
        Route::get('{tenant}/features', [FeatureController::class, 'getTenantFeatures'])->name('features.index');
        Route::post('{tenant}/features/{feature}', [FeatureController::class, 'assignFeatureToTenant'])->name('features.assign');
        Route::post('{tenant}/features', [FeatureController::class, 'bulkUpdateTenantFeatures'])->name('features.bulk-update');

        // ── Bundles (apply as template to tenant) ─────────────────────────
        Route::post('{tenant}/bundles/{featureBundle}/apply', [FeatureBundleController::class, 'applyBundleToTenant'])->name('bundles.apply');
    });

    // ── Feature management ─────────────────────────────────────────────────
    Route::prefix('features')->name('features.')->group(function () {
        Route::get('/', [FeatureController::class, 'index'])->name('index');
        Route::post('/', [FeatureController::class, 'store'])->name('store');
        Route::post('seed', [FeatureController::class, 'seedDefaultFeatures'])->name('seed');
        Route::put('{feature}', [FeatureController::class, 'update'])->name('update');
        Route::delete('{feature}', [FeatureController::class, 'destroy'])->name('destroy');
    });

    // ── Bundle management (CRUD + features) ───────────────────────────────
    Route::prefix('feature-bundles')->name('feature-bundles.')->group(function () {
        Route::get('/', [FeatureBundleController::class, 'index'])->name('index');
        Route::post('/', [FeatureBundleController::class, 'store'])->name('store');
        Route::post('seed', [FeatureBundleController::class, 'seedDefaultBundles'])->name('seed');
        Route::get('{featureBundle}', [FeatureBundleController::class, 'show'])->name('show');
        Route::put('{featureBundle}', [FeatureBundleController::class, 'update'])->name('update');
        Route::delete('{featureBundle}', [FeatureBundleController::class, 'destroy'])->name('destroy');
        Route::post('{featureBundle}/features', [FeatureBundleController::class, 'syncFeatures'])->name('features.sync');
    });
});
