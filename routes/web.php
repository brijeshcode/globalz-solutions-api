<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');