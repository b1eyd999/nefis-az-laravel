<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin speaks the owner's language.
 *
 * Every word the shop writes into the panel is Azerbaijani, but Filament's
 * own words — the breadcrumb, the search box, the empty tables, the buttons
 * in its dialogs — come from its translation files, and those follow the
 * application's locale. The panel has no locale middleware of its own, so it
 * was running on APP_LOCALE, which is `en` on this installation. The result
 * read as half a language: «Rəylər · List», «Search», and an empty table
 * saying «No rəylər».
 *
 * Only the panel's own requests are touched. The shop's pages keep setting
 * their language from the address, as they always have.
 */
class AdminInAzerbaijani
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::DEFAULT);

        return $next($request);
    }
}
