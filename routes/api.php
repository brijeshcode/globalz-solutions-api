<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Setups\ItemBrandsController;
use App\Http\Controllers\Api\Setups\ItemCategoriesController;
use App\Http\Controllers\Api\Setups\ItemFamiliesController;
use App\Http\Controllers\Api\Setups\ItemGroupsController;
use App\Http\Controllers\Api\Setups\ItemTypesController;
use App\Http\Controllers\Api\Setups\ItemUnitController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected routes (authentication required)
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::prefix('setups')->name('setups.')->group(function () {
        Route::prefix('items')->name('items.')->group(function () {

            // Item Units Controller
            Route::controller(ItemUnitController::class)->prefix('units')->name('units.')->group(function () {
                Route::get('active', 'active')->name('active');
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{unit}', 'show')->name('show');
                Route::put('{unit}', 'update')->name('update');
                Route::delete('{unit}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Types Controller
            Route::controller(ItemTypesController::class)->prefix('types')->name('types.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{type}', 'show')->name('show');
                Route::put('{type}', 'update')->name('update');
                Route::delete('{type}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Families Controller
            Route::controller(ItemFamiliesController::class)->prefix('families')->name('families.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{family}', 'show')->name('show');
                Route::put('{family}', 'update')->name('update');
                Route::delete('{family}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Groups Controller
            Route::controller(ItemGroupsController::class)->prefix('groups')->name('groups.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{group}', 'show')->name('show');
                Route::put('{group}', 'update')->name('update');
                Route::delete('{group}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Brands Controller
            Route::controller(ItemBrandsController::class)->prefix('brands')->name('brands.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{brand}', 'show')->name('show');
                Route::put('{brand}', 'update')->name('update');
                Route::delete('{brand}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

            // Item Categories Controller
            Route::controller(ItemCategoriesController::class)->prefix('categories')->name('categories.')->group(function () {
                Route::get('trashed', 'trashed')->name('trashed');
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('{category}', 'show')->name('show');
                Route::put('{category}', 'update')->name('update');
                Route::delete('{category}', 'destroy')->name('destroy');
                Route::patch('{id}/restore', 'restore')->name('restore');
                Route::delete('{id}/force-delete', 'forceDelete')->name('force-delete');
            });

        });
    });
});