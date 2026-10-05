<?php

use App\Http\Controllers\BoxEditorController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CourierController;
use App\Http\Controllers\CoverController;
use App\Http\Controllers\EpointController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GiftPageController;
use App\Http\Controllers\LivePhotoController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\OrderFileController;
use App\Http\Controllers\Phone\LiveController as PhoneLive;
use App\Http\Controllers\Phone\MoneyController as PhoneMoney;
use App\Http\Controllers\Phone\OrderController as PhoneOrders;
use App\Http\Controllers\Phone\StockController as PhoneStock;
use App\Http\Controllers\Phone\TaskController as PhoneTasks;
use App\Http\Controllers\PlaceMapController;
use App\Http\Controllers\PlacePrintController;
use App\Http\Controllers\SceneEditorController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StarMapController;
use App\Http\Controllers\TelegramController;
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
// Azerbaijani unless the order it belongs to was placed in another language
// (the controller looks); never whatever the server's own locale happens to be.
Route::get('/canli/{code}', [LivePhotoController::class, 'show'])->where('code', '[a-z0-9]{4,16}')->middleware('locale')->name('live.show');
Route::get('/canli/{code}/video', [LivePhotoController::class, 'video'])->where('code', '[a-z0-9]{4,16}')->name('live.video');
Route::post('/canli-hazirla/{livePhoto}', [LivePhotoController::class, 'storeMind'])->middleware('auth')->name('live.mind');

/* ePoint, paying by card. The result address is the one the gateway itself
   calls, server to server: no session, no language prefix, and the signature
   inside is what makes it trustworthy. The other two are where the bank sends
   the customer back, and they decide nothing. */
Route::post('/epoint/result', [EpointController::class, 'result'])->name('epoint.result');
Route::get('/epoint/ugurlu/{order}', [EpointController::class, 'done'])
    ->whereNumber('order')->middleware('auth')->name('epoint.done');
Route::get('/epoint/xeta/{order}', [EpointController::class, 'failed'])
    ->whereNumber('order')->middleware('auth')->name('epoint.failed');

// A courier taps "I'll take it" in the group and Telegram calls this address.
Route::post('/telegram/kuryer/{secret}', [TelegramController::class, 'courier'])
    ->where('secret', '[a-f0-9]{32}')
    ->name('telegram.courier');

// The chat window on the site: the visitor writes here, the shop answers from
// Telegram and Telegram calls the last of these three.
Route::middleware(['throttle:30,1', 'data'])->prefix('sohbet')->name('chat.')->group(function () {
    Route::post('/yaz', [ChatController::class, 'send'])->name('send');
    Route::get('/oxu', [ChatController::class, 'poll'])->name('poll');
});
Route::post('/telegram/sohbet/{secret}', [ChatController::class, 'hook'])
    ->where('secret', '[A-Za-z0-9]{32}')
    ->name('telegram.chat');

// Address lookups for the checkout map (OpenStreetMap), asked through the site.
Route::middleware(['throttle:40,1', 'data'])->prefix('xerite')->name('map.')->group(function () {
    Route::get('/unvan', [MapController::class, 'reverse'])->name('reverse');
    Route::get('/axtar', [MapController::class, 'search'])->name('search');
});

// The streets behind a map box, and the search that finds a place. Separate
// from the `map.` routes above: those are the checkout's delivery map and are
// bounded to Baku, while a box may hold any corner of the world.
Route::middleware(['throttle:60,1', 'data'])->prefix('lokasiya')->name('place.')->group(function () {
    Route::get('/sekil', [PlaceMapController::class, 'image'])->name('image');
    Route::get('/axtar', [PlaceMapController::class, 'search'])->name('search');
    Route::get('/kafel/{style}/{z}/{x}/{y}', [PlaceMapController::class, 'tile'])
        ->where(['style' => '[a-z]+', 'z' => '[0-9]{1,2}', 'x' => '[0-9]+', 'y' => '[0-9]+'])
        ->name('tile');
});

// ---------------------------------------------------------------------------
// The customer's site, once per language.
// ---------------------------------------------------------------------------

/** Gift pages are read in search results, so their addresses are words. */
$giftPaths = ['az' => 'hediyye', 'ru' => 'podarki', 'en' => 'gifts'];

foreach (Locale::all() as $locale) {
    Route::prefix($locale === Locale::DEFAULT ? '' : $locale)
        ->name($locale === Locale::DEFAULT ? '' : $locale.'.')
        ->middleware('locale:'.$locale)
        ->group(function () use ($locale, $giftPaths) {
            require base_path('routes/public.php');

            Route::get('/'.$giftPaths[$locale], [GiftPageController::class, 'index'])->name('gifts.index');
            Route::get('/'.$giftPaths[$locale].'/{giftPage:slug}', [GiftPageController::class, 'show'])->name('gifts.show');
        });
}

// The Russian gift pages were found at /podarki before the language moved into
// the address; search engines are sent on to where they live now.
Route::permanentRedirect('/podarki', '/ru/podarki');
Route::get('/podarki/{slug}', fn (string $slug) => redirect('/ru/podarki/'.$slug, 301))
    ->where('slug', '[A-Za-z0-9-]+');

// ---------------------------------------------------------------------------
// The owner's own tools, in Azerbaijani only.
// ---------------------------------------------------------------------------

/*
 * The shop in a pocket. Its own small pages rather than the desktop panel
 * squeezed into a phone: the owner runs the day from here — what is due, whose
 * order it is, where the money stands, what the stock is down to. Azerbaijani
 * only and outside the language prefixes, because it is not a page for
 * customers; 'locale' with no argument keeps it Azerbaijani whatever the app
 * default says.
 */
// One order line's star map, drawn at printing size for the workshop.
Route::get('/admin-ulduz/{item}', [StarMapController::class, 'show'])
    ->middleware(['auth', 'staff'])->name('star.print');

// And one order line's location map, the same way.
Route::get('/admin-lokasiya/{item}', [PlacePrintController::class, 'show'])
    ->middleware(['auth', 'staff'])->name('place.print');

// A customer's own picture off an order line, handed over as a download
// rather than shown — the workshop prints these, it does not look at them.
Route::get('/admin-fayl/{item}/{which}', [OrderFileController::class, 'show'])
    ->where('which', 'mektub|[0-9]{1,2}')
    ->middleware(['auth', 'staff'])->name('order.file');

Route::middleware(['auth', 'locale', 'staff'])->prefix('admin-phone')->name('phone.')->group(function () {
    Route::get('/', [PhoneOrders::class, 'index'])->name('orders.index');
    Route::get('/sifarish/{order}', [PhoneOrders::class, 'show'])->whereNumber('order')->name('orders.show');

    // The work board and the notes: everyone who works here keeps them.
    Route::get('/isler', [PhoneTasks::class, 'index'])->name('tasks.index');
    Route::post('/isler', [PhoneTasks::class, 'store'])->name('tasks.store');
    Route::patch('/isler/{task}', [PhoneTasks::class, 'update'])->whereNumber('task')->name('tasks.update');
    Route::delete('/isler/{task}', [PhoneTasks::class, 'destroy'])->whereNumber('task')->name('tasks.destroy');
    Route::post('/sifarish/{order}/status', [PhoneOrders::class, 'status'])->whereNumber('order')->name('orders.status');
    Route::post('/sifarish/{order}/odenis-tesdiq', [PhoneOrders::class, 'confirmPayment'])
        ->whereNumber('order')->name('orders.pay');
    // Whose delivery it is, and the line the customer waits for.
    Route::post('/sifarish/{order}/kuryer', [PhoneOrders::class, 'courier'])
        ->whereNumber('order')->name('orders.courier');
    Route::post('/sifarish/{order}/yolda', [PhoneOrders::class, 'way'])
        ->whereNumber('order')->name('orders.way');

    // The books, the shelves and the customers' videos are the owner's alone.
    Route::middleware('owner')->group(function () {
        Route::get('/kassa', [PhoneMoney::class, 'index'])->name('money.index');
        Route::post('/kassa/xerc', [PhoneMoney::class, 'storeExpense'])->name('money.expense');
        Route::post('/kassa/magaza', [PhoneMoney::class, 'toggleShop'])->name('money.shop');

        Route::get('/anbar', [PhoneStock::class, 'index'])->name('stock.index');
        Route::post('/anbar/{material}/alis', [PhoneStock::class, 'purchase'])->whereNumber('material')->name('stock.purchase');
        Route::post('/anbar/{material}/sayim', [PhoneStock::class, 'adjust'])->whereNumber('material')->name('stock.adjust');

        Route::get('/canli', [PhoneLive::class, 'index'])->name('live.index');
        Route::post('/canli/kocur', [PhoneLive::class, 'push'])->name('live.push');
    });
});

/*
 * The courier's own screen. Not the panel and not the phone admin: he is not
 * staff, and that is the line both of those draw. Azerbaijani, outside the
 * language prefixes, and every page checks the order it shows is his.
 */
Route::middleware(['auth', 'locale', 'courier'])->prefix('kuryer')->name('courier.')->group(function () {
    Route::get('/', [CourierController::class, 'index'])->name('index');
    Route::get('/sifarish/{order}', [CourierController::class, 'show'])->whereNumber('order')->name('show');
    Route::post('/sifarish/{order}/yolda', [CourierController::class, 'onTheWay'])->whereNumber('order')->name('way');
    Route::post('/sifarish/{order}/tehvil', [CourierController::class, 'delivered'])->whereNumber('order')->name('delivered');
    Route::post('/paylas', [CourierController::class, 'share'])->name('share');
    /* His phone's own readings. Throttled by the account rather than the
       address — several couriers behind one mobile network share an address —
       and marked as data, so a page never comes back to it. */
    Route::post('/yer', [CourierController::class, 'position'])
        ->middleware(['throttle:60,1', 'data'])->name('position');
});

// Where the couriers are, for the owner's map in the panel.
Route::get('/admin-kuryerler/yerler', [CourierController::class, 'live'])
    ->middleware(['auth', 'staff', 'data'])->name('couriers.live');

Route::middleware('auth')->group(function () {
    // The admin's box editor: artwork layers, photo areas and captions.
    Route::prefix('qutu-redaktoru/{product:slug}')->name('box.')->group(function () {
        Route::get('/', [BoxEditorController::class, 'edit'])->name('edit');
        Route::post('/', [BoxEditorController::class, 'save'])->name('save');
        Route::post('/asset', [BoxEditorController::class, 'uploadAsset'])->name('asset');
        Route::post('/kitabxana/{asset}', [BoxEditorController::class, 'useLibrary'])->whereNumber('asset')->name('library');
        Route::post('/kopyala', [BoxEditorController::class, 'copyAsset'])->name('copy');
        Route::get('/sablonlar', [BoxEditorController::class, 'templates'])->name('templates');
        Route::post('/sablon', [BoxEditorController::class, 'keepTemplate'])->name('template.keep');
        Route::post('/sablon/isle', [BoxEditorController::class, 'useTemplate'])->name('template.use');
        Route::delete('/sablon/{template}', [BoxEditorController::class, 'forgetTemplate'])->whereNumber('template')->name('template.forget');
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
