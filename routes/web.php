<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BoxEditorController;
use App\Http\Controllers\CoverController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GiftPageController;
use App\Http\Controllers\LivePhotoController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\SceneEditorController;
use App\Http\Controllers\SitemapController;
use App\Support\Locale;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// The same in every language: files, feeds and the pages a QR code opens.
// ---------------------------------------------------------------------------

// Artwork lives on Yandex Disk, not on this hosting; this only redirects.
Route::get('/i/{path}', [MediaController::class, 'show'])
    ->where('path', '[A-Za-z0-9][A-Za-z0-9._/-]*')
    ->name('media');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
// The designs as a product feed, for Google Merchant Center's free listings.
Route::get('/feed.xml', [FeedController::class, 'index'])->name('feed');

// A live photo's own page: the address is printed on the box, so it never moves.
Route::get('/canli/{code}', [LivePhotoController::class, 'show'])->where('code', '[a-z0-9]{4,16}')->name('live.show');
Route::get('/canli/{code}/video', [LivePhotoController::class, 'video'])->where('code', '[a-z0-9]{4,16}')->name('live.video');
Route::post('/canli-hazirla/{livePhoto}', [LivePhotoController::class, 'storeMind'])->middleware('auth')->name('live.mind');

// Address lookups for the checkout map (OpenStreetMap), asked through the site.
Route::middleware('throttle:40,1')->prefix('xerite')->name('map.')->group(function () {
    Route::get('/unvan', [MapController::class, 'reverse'])->name('reverse');
    Route::get('/axtar', [MapController::class, 'search'])->name('search');
});

// ---------------------------------------------------------------------------
// The customer's site, once per language.
// ---------------------------------------------------------------------------

/** Gift pages are read in search results, so their addresses are words. */
$giftPaths = ['az' => 'hediyye', 'ru' => 'podarki', 'en' => 'gifts'];

foreach (Locale::all() as $locale) {
    Route::prefix($locale === Locale::DEFAULT ? '' : $locale)
        ->name($locale === Locale::DEFAULT ? '' : $locale . '.')
        ->middleware('locale:' . $locale)
        ->group(function () use ($locale, $giftPaths) {
            require base_path('routes/public.php');

            Route::get('/' . $giftPaths[$locale], [GiftPageController::class, 'index'])->name('gifts.index');
            Route::get('/' . $giftPaths[$locale] . '/{giftPage:slug}', [GiftPageController::class, 'show'])->name('gifts.show');
        });
}

// The Russian gift pages were found at /podarki before the language moved into
// the address; search engines are sent on to where they live now.
Route::permanentRedirect('/podarki', '/ru/podarki');
Route::get('/podarki/{slug}', fn (string $slug) => redirect('/ru/podarki/' . $slug, 301))
    ->where('slug', '[A-Za-z0-9-]+');

// ---------------------------------------------------------------------------
// The owner's own tools, in Azerbaijani only.
// ---------------------------------------------------------------------------

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

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
});
