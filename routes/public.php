<?php

/**
 * Everything a customer sees, registered once for each language: at the root
 * in Azerbaijani, under /ru and /en in the other two. The gift pages are the
 * exception — their addresses are words in their own language, so they are
 * added next to this file, in routes/web.php.
 */

use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartHandoffController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CorporateController;
use App\Http\Controllers\EpointController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LetterController;
use App\Http\Controllers\LivePhotoController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\WrappingController;
use App\Http\Controllers\XoncaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/dizaynlar', [ProductController::class, 'index'])->name('designs.index');

/* "No signal", in this language. A page of its own rather than a message the
   worker invents, so it is the shop that is speaking even with no network. */
Route::get('/oflayn', [PwaController::class, 'offline'])->name('offline');
Route::get('/products/{product:slug}/customize', [ProductController::class, 'customize'])->name('products.customize');
Route::get('/qablasdirma', [WrappingController::class, 'index'])->name('wrappings.index');

Route::get('/mektub', [LetterController::class, 'create'])->name('letters.create');
Route::post('/mektub', [LetterController::class, 'store'])->middleware('throttle:10,60')->name('letters.store');

/* The small chocolate a company puts its own logo on. Priced by the number,
   so the page ends in a request rather than a basket. */
Route::get('/sirketler-ucun', [CorporateController::class, 'index'])->name('corporate.index');
Route::post('/sirketler-ucun', [CorporateController::class, 'store'])->middleware('throttle:10,60')->name('corporate.store');

Route::get('/canli-sekil', [LivePhotoController::class, 'create'])->name('live.create');

/* The small chocolates a xonça is piled with, for an engagement, a henna
   night or a wedding: their own designs, their own page. */
Route::get('/xonca', [XoncaController::class, 'index'])->name('xonca.index');
/* Anybody at all may post an 18 MB video here. Ten an hour from one address
   is far more than a customer needs and keeps the disk out of a stranger's
   hands. */
Route::post('/canli-sekil', [LivePhotoController::class, 'store'])->middleware('throttle:10,60')->name('live.store');

// The shop's own rules: how an order works, what we know about a customer,
// and when money comes back. Plain pages, one per subject.
Route::view('/qaydalar', 'legal.terms')->name('legal.terms');
Route::view('/mexfilik', 'legal.privacy')->name('legal.privacy');
Route::view('/odenis-ve-qaytarma', 'legal.refund')->name('legal.refund');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
Route::delete('/cart/{id}', [CartController::class, 'remove'])->name('cart.remove');

/* A basket the shop filled for one customer. The address is the whole key —
   whoever holds it holds the basket — so the token is long and random, and
   the road is slowed down against anyone trying tokens one after another. */
Route::get('/hazir-sebet/{token}', [CartHandoffController::class, 'open'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->middleware('throttle:20,1')
    ->name('cart.handoff.open');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,10');
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

    // The owner turns the basket he has just built into a link to send.
    Route::post('/hazir-sebet', [CartHandoffController::class, 'store'])->name('cart.handoff.store');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    // Does this code work, and what does it take off? Only what the customer
    // is shown — the charge itself is worked out again when the order is sent.
    Route::post('/promokod', [CheckoutController::class, 'promo'])
        ->middleware('throttle:20,1')->name('checkout.promo');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');

    /* The customer's own page. Until now he could sign in and look at his
       orders and nothing else — a number typed wrong at the sign-up stayed
       wrong, and a new password meant saying you had forgotten the old. */
    Route::get('/hesabim', [ProfileController::class, 'show'])->name('profile.index');
    Route::post('/hesabim', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/hesabim/sifre', [ProfileController::class, 'password'])
        ->middleware('throttle:10,10')->name('profile.password');

    // Paying by transfer, and the receipt that follows it.
    Route::prefix('sifaris/{order}')->whereNumber('order')->name('orders.')->group(function () {
        Route::get('/odenis', [PaymentController::class, 'show'])->name('pay');
        // Ordered and thought better of it, before any money moved.
        Route::post('/legv', [OrderController::class, 'cancel'])->name('cancel');
        Route::post('/odenis/usul', [PaymentController::class, 'method'])->name('pay.method');
        Route::post('/odenis/cek', [PaymentController::class, 'receipt'])->name('pay.receipt');
        // Paying by card: the customer is sent to the bank's own page.
        Route::post('/odenis/kart', [EpointController::class, 'start'])->name('pay.card');
        // Google Pay and Apple Pay, in a window on this page rather than away
        // at the bank's. Throttled: each call opens a payment at the gateway.
        Route::post('/odenis/cuzdan', [EpointController::class, 'wallet'])
            ->middleware('throttle:10,1')->name('pay.wallet');

        // The order changed after it was paid for, and the difference is owed.
        // Its own page, gated on the change rather than on the order's status.
        Route::prefix('elave/{adjustment}')->whereNumber('adjustment')->name('extra.')->group(function () {
            Route::get('/', [AdjustmentController::class, 'show'])->name('show');
            Route::post('/kart', [AdjustmentController::class, 'card'])->name('card');
            Route::post('/cek', [AdjustmentController::class, 'receipt'])->name('receipt');
        });
    });
});
