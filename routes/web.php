<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', SearchController::class)->name('catalog.search');

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');

