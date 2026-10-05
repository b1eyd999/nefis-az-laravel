<?php

/**
 * Everything a customer sees, registered once for each language: at the root
 * in Azerbaijani, under /ru and /en in the other two. The gift pages are the
 * exception — their addresses are words in their own language, so they are
 * added next to this file, in routes/web.php.
 */

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CorporateController;
use App\Http\Controllers\EpointController;
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

/* The small chocolate a company puts its own logo on. Priced by the number,
   so the page ends in a request rather than a basket. */
Route::get('/sirketler-ucun', [CorporateController::class, 'index'])->name('corporate.index');
Route::post('/sirketler-ucun', [CorporateController::class, 'store'])->name('corporate.store');

Route::get('/canli-sekil', [LivePhotoController::class, 'create'])->name('live.create');
Route::post('/canli-sekil', [LivePhotoController::class, 'store'])->name('live.store');

// The shop's own rules: how an order works, what we know about a customer,
// and when money comes back. Plain pages, one per subject.
Route::view('/qaydalar', 'legal.terms')->name('legal.terms');
Route::view('/mexfilik', 'legal.privacy')->name('legal.privacy');
Route::view('/odenis-ve-qaytarma', 'legal.refund')->name('legal.refund');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::delete('/cart/{id}', [CartController::class, 'remove'])->name('cart.remove');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // A forgotten password: ask by e-mail, then set a new one with the link.
    Route::get('/sifre-unutdum', [PasswordController::class, 'showRequest'])->name('password.request');
    Route::post('/sifre-unutdum', [PasswordController::class, 'sendLink'])->name('password.email');
    Route::get('/sifre-yenile/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/sifre-yenile', [PasswordController::class, 'reset'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    /* Registered once per language, like the login: a customer who signs out
       of the Russian site must land back on the Russian site. Outside the
       language groups the address says nothing, and the page then came back
       in whatever language the server itself is set to. */
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    // Does this code work, and what does it take off? Only what the customer
    // is shown — the charge itself is worked out again when the order is sent.
    Route::post('/promokod', [CheckoutController::class, 'promo'])
        ->middleware('throttle:20,1')->name('checkout.promo');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    // Paying by transfer, and the receipt that follows it.
    Route::prefix('sifaris/{order}')->whereNumber('order')->name('orders.')->group(function () {
        Route::get('/odenis', [PaymentController::class, 'show'])->name('pay');
        Route::post('/odenis/usul', [PaymentController::class, 'method'])->name('pay.method');
        Route::post('/odenis/cek', [PaymentController::class, 'receipt'])->name('pay.receipt');
        // Paying by card: the customer is sent to the bank's own page.
        Route::post('/odenis/kart', [EpointController::class, 'start'])->name('pay.card');

        // The order changed after it was paid for, and the difference is owed.
        // Its own page, gated on the change rather than on the order's status.
        Route::prefix('elave/{adjustment}')->whereNumber('adjustment')->name('extra.')->group(function () {
            Route::get('/', [\App\Http\Controllers\AdjustmentController::class, 'show'])->name('show');
            Route::post('/kart', [\App\Http\Controllers\AdjustmentController::class, 'card'])->name('card');
            Route::post('/cek', [\App\Http\Controllers\AdjustmentController::class, 'receipt'])->name('receipt');
        });
    });
});
