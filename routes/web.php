<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\Seller\ActivationController;
use App\Http\Controllers\Seller\RegistrationController as SellerRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'show'])->defaults('slug', 'home')->name('home');

Route::get('/search', SearchController::class)->name('catalog.search');

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');

Route::get('/register', [RegistrationController::class, 'create'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');

Route::get('/seller/register', [SellerRegistrationController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [SellerRegistrationController::class, 'store'])->name('seller.register.store');
Route::view('/seller/register/submitted', 'seller.registration-submitted')->name('seller.registration.submitted');

Route::get('/seller/activate/{seller}', [ActivationController::class, 'show'])->middleware('signed')->name('seller.activate');
Route::post('/seller/activate/{seller}', [ActivationController::class, 'store'])->middleware('signed')->name('seller.activate.store');

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

