<?php

use App\Http\Controllers\SamiraController;
use App\Http\Middleware\SamiraOnly;
use Illuminate\Support\Facades\Route;

/*
 * Everything here is under /api and outside the `web` group on purpose: no
 * session, no cookies, no CSRF form token, no maintenance page (the owner's
 * assistant goes on reading while the shop is closed to customers), and no
 * CORS — config/cors.php lists no paths at all, so no browser page on another
 * site may read an answer even with the key.
 *
 * The throttle counts every call from an address, refused ones included,
 * and comes first so a guessed key costs the guesser a minute per sixty.
 */
Route::middleware(['throttle:60,1', SamiraOnly::class])
    ->prefix('samira')->name('samira.')
    ->group(function () {
        Route::get('feed', [SamiraController::class, 'feed'])->name('feed');
        Route::get('order/{number}', [SamiraController::class, 'order'])
            ->where('number', '[0-9]{1,10}')->name('order');
    });
