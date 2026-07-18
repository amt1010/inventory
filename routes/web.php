<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\QuoteRequestController;
use App\Http\Controllers\QuoteRequestHistoryController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Seller\ActivationController;
use App\Http\Controllers\Seller\RegistrationController as SellerRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'show'])->defaults('slug', 'home')->name('home');

Route::get('/search', SearchController::class)->name('catalog.search');

Route::post('/quote-requests', [QuoteRequestController::class, 'store'])->name('quote-requests.store');

Route::get('/register', [RegistrationController::class, 'create'])->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware('throttle:6,1')->name('register.store');

Route::get('/login', [SessionController::class, 'create'])->name('login');
Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

Route::get('/seller/register', [SellerRegistrationController::class, 'create'])->name('seller.register');
Route::post('/seller/register', [SellerRegistrationController::class, 'store'])->name('seller.register.store');
Route::view('/seller/register/submitted', 'seller.registration-submitted')->name('seller.registration.submitted');

Route::get('/seller/activate/{seller}', [ActivationController::class, 'show'])->middleware('signed')->name('seller.activate');
Route::post('/seller/activate/{seller}', [ActivationController::class, 'store'])->middleware('signed')->name('seller.activate.store');

Route::middleware('auth:web')->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites', [FavoriteController::class, 'store'])->name('favorites.store');
    Route::delete('/favorites/{product}', [FavoriteController::class, 'destroy'])->name('favorites.destroy');
    Route::get('/my-quote-requests', [QuoteRequestHistoryController::class, 'index'])->name('quote-requests.history');
});

Route::middleware('auth:staff')->group(function () {
    Route::get('/preview/product/{product}', [PreviewController::class, 'product'])->name('staff.preview.product');
    Route::get('/preview/category/{category}', [PreviewController::class, 'category'])->name('staff.preview.category');
});

Route::get('/products/{path?}', [CatalogController::class, 'show'])
    ->where('path', '.*')
    ->name('catalog.show');

Route::get('/{slug}', [PageController::class, 'show'])->name('pages.show');

