<?php

/**
 * Everything a customer sees, registered once for each language: at the root
 * in Azerbaijani, under /ru and /en in the other two. The gift pages are the
 * exception — their addresses are words in their own language, so they are
 * added next to this file, in routes/web.php.
 */

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LivePhotoController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WrappingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/dizaynlar', [ProductController::class, 'index'])->name('designs.index');
Route::get('/products/{product:slug}/customize', [ProductController::class, 'customize'])->name('products.customize');
Route::get('/qablasdirma', [WrappingController::class, 'index'])->name('wrappings.index');

Route::get('/mektub', [LetterController::class, 'create'])->name('letters.create');
Route::post('/mektub', [LetterController::class, 'store'])->name('letters.store');

Route::get('/canli-sekil', [LivePhotoController::class, 'create'])->name('live.create');
Route::post('/canli-sekil', [LivePhotoController::class, 'store'])->name('live.store');

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
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Paying by transfer, and the receipt that follows it.
    Route::prefix('sifaris/{order}')->whereNumber('order')->name('orders.')->group(function () {
        Route::get('/odenis', [PaymentController::class, 'show'])->name('pay');
        Route::post('/odenis/usul', [PaymentController::class, 'method'])->name('pay.method');
        Route::post('/odenis/cek', [PaymentController::class, 'receipt'])->name('pay.receipt');
    });
});
