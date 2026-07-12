<?php

use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');

