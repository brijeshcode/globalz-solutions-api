<?php

use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserSettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Settings API Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Global Settings Routes (Admin only)
    Route::middleware(['role:super_admin,admin'])->prefix('settings')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::get('/data-types', [SettingsController::class, 'getDataTypes']);
        Route::get('/export', [SettingsController::class, 'export']);
        Route::post('/import', [SettingsController::class, 'import']);
        Route::post('/clear-cache', [SettingsController::class, 'clearCache']);
        Route::post('/multiple', [SettingsController::class, 'updateMultiple']);
        Route::post('/', [SettingsController::class, 'createSetting']);
        
        Route::get('/{group}', [SettingsController::class, 'getGroup']);
        Route::get('/{group}/{key}', [SettingsController::class, 'getSetting']);
        Route::put('/{group}/{key}', [SettingsController::class, 'updateSetting']);
        Route::delete('/{group}/{key}', [SettingsController::class, 'deleteSetting']);
    });

    // User Settings Routes
    Route::prefix('user-settings')->group(function () {
        // Current user settings
        Route::get('/', [UserSettingsController::class, 'index']);
        Route::post('/', [UserSettingsController::class, 'store']);
        Route::post('/multiple', [UserSettingsController::class, 'updateMultiple']);
        Route::post('/reset', [UserSettingsController::class, 'reset']);
        
        Route::get('/theme', [UserSettingsController::class, 'getTheme']);
        Route::put('/theme', [UserSettingsController::class, 'updateTheme']);
        
        Route::get('/notifications', [UserSettingsController::class, 'getNotificationPreferences']);
        Route::put('/notifications', [UserSettingsController::class, 'updateNotificationPreferences']);
        
        Route::get('/{key}', [UserSettingsController::class, 'show']);
        Route::put('/{key}', [UserSettingsController::class, 'update']);
        Route::delete('/{key}', [UserSettingsController::class, 'destroy']);

        // Admin routes for managing other users' settings
        Route::middleware(['role:super_admin,admin'])->group(function () {
            Route::get('/user/{userId}', [UserSettingsController::class, 'getForUser']);
            Route::put('/user/{userId}', [UserSettingsController::class, 'updateForUser']);
        });
    });

});