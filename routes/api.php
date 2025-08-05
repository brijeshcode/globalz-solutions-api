<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Setups\FamiliesController;
use App\Http\Controllers\Api\Setups\GroupsController;
use App\Http\Controllers\Api\Setups\TypesController;
use App\Http\Controllers\Api\Setups\UnitController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);

    // Units
    
    Route::prefix('setups')->name('setups.')->group(function () {
        Route::get('units/active', [UnitController::class, 'active'])->name('units.active');
        Route::apiResource('units', UnitController::class);

        Route::prefix('types')->name('types.')->group(function () {
            Route::get('trashed', [TypesController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [TypesController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [TypesController::class, 'forceDelete'])->name('force-delete');
        });
        
        Route::apiResource('types', TypesController::class)->names([
            'index' => 'types.index',
            'store' => 'types.store',
            'show' => 'types.show',
            'update' => 'types.update',
            'destroy' => 'types.destroy',
        ]);

        // families setup
        Route::prefix('families')->name('families.')->group(function () {
            Route::get('trashed', [FamiliesController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [FamiliesController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [FamiliesController::class, 'forceDelete'])->name('force-delete');
        });
        
        Route::apiResource('families', FamiliesController::class)->names([
            'index' => 'families.index',
            'store' => 'families.store',
            'show' => 'families.show',
            'update' => 'families.update',
            'destroy' => 'families.destroy',
        ]);

        // Groups
        Route::prefix('groups')->name('groups.')->group(function () {
            Route::get('trashed', [GroupsController::class, 'trashed'])->name('trashed');
            Route::patch('{id}/restore', [GroupsController::class, 'restore'])->name('restore');
            Route::delete('{id}/force-delete', [GroupsController::class, 'forceDelete'])->name('force-delete');
        });
        
        Route::apiResource('groups', GroupsController::class)->names([
            'index' => 'groups.index',
            'store' => 'groups.store',
            'show' => 'groups.show',
            'update' => 'groups.update',
            'destroy' => 'groups.destroy',
        ]);
    });
    
});