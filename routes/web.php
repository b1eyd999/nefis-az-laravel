<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BoxEditorController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Artwork lives on Yandex Disk, not on this hosting; this only redirects.
Route::get('/i/{path}', [MediaController::class, 'show'])
    ->where('path', '[A-Za-z0-9][A-Za-z0-9._/-]*')
    ->name('media');

Route::get('/dizaynlar', [ProductController::class, 'index'])->name('designs.index');
Route::get('/products/{product:slug}/customize', [ProductController::class, 'customize'])->name('products.customize');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::delete('/cart/{id}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    // The admin's box editor: artwork layers, photo areas and captions.
    Route::prefix('qutu-redaktoru/{product:slug}')->name('box.')->group(function () {
        Route::get('/', [BoxEditorController::class, 'edit'])->name('edit');
        Route::post('/', [BoxEditorController::class, 'save'])->name('save');
        Route::post('/asset', [BoxEditorController::class, 'uploadAsset'])->name('asset');
        Route::post('/visual', [BoxEditorController::class, 'uploadVisual'])->name('visual');
        Route::post('/font', [BoxEditorController::class, 'uploadFont'])->name('font');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});
