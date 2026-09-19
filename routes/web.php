<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BoxEditorController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\CoverController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SceneEditorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Artwork lives on Yandex Disk, not on this hosting; this only redirects.
Route::get('/i/{path}', [MediaController::class, 'show'])
    ->where('path', '[A-Za-z0-9][A-Za-z0-9._/-]*')
    ->name('media');

Route::get('/dizaynlar', [ProductController::class, 'index'])->name('designs.index');

// Address lookups for the checkout map (OpenStreetMap), asked through the site.
Route::middleware('throttle:40,1')->prefix('xerite')->name('map.')->group(function () {
    Route::get('/unvan', [MapController::class, 'reverse'])->name('reverse');
    Route::get('/axtar', [MapController::class, 'search'])->name('search');
});
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

    // Catalogue covers, drawn in the admin's browser and uploaded.
    Route::get('/qapaq-yarat', [CoverController::class, 'page'])->name('cover.page');
    Route::post('/qapaq/{product}', [CoverController::class, 'store'])->whereNumber('product')->name('cover.store');

    // The admin's scene editor: the mockups customers see their box in.
    Route::prefix('sehne-redaktoru')->name('scene.')->group(function () {
        Route::post('/kitabxana', [SceneEditorController::class, 'uploadAsset'])->name('asset');
        Route::delete('/kitabxana/{asset}', [SceneEditorController::class, 'destroyAsset'])->name('asset.destroy');
        Route::get('/{scene}', [SceneEditorController::class, 'edit'])->whereNumber('scene')->name('edit');
        Route::post('/{scene}', [SceneEditorController::class, 'save'])->whereNumber('scene')->name('save');
    });

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
});
