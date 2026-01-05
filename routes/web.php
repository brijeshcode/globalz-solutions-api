<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

// SPA fallback route - only for non-API routes
Route::get('/{any}', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return file_get_contents($indexPath);
    }
    return response()->json([
        'message' => 'Welcome to GlobalZ Solutions API',
        'status' => 'active'
    ]);
})->where('any', '^(?!api).*$'); // Exclude routes starting with 'api'