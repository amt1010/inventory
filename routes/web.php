<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Seller\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/search', SearchController::class)->name('catalog.search');

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');

Route::get('/seller/register', [RegistrationController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [RegistrationController::class, 'store'])->name('seller.register.store');
Route::view('/seller/register/submitted', 'seller.registration-submitted')->name('seller.registration.submitted');

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');

