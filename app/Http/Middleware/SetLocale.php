<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Which language a page is in is decided by its address, not by a cookie or
 * by the browser's own settings: /ru/… is Russian for everyone, which is what
 * a link sent to a friend has to keep and what search engines need.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next, ?string $locale = null): Response
    {
        /* The route says so for a page that belongs to a language group.
           For everything else the address itself is still the answer, and
           reading it here is what lets the pages OUTSIDE those groups speak
           the reader's language: the maintenance page, which is shown by a
           middleware of the web group and so never reaches `locale:ru`, and
           the refusals thrown before routing — a session that timed out, an
           upload too big for the hosting. All of them used to come back in
           Azerbaijani however the visitor had arrived. */
        $locale ??= self::fromAddress($request);

        app()->setLocale(in_array($locale, Locale::all(), true) ? $locale : Locale::DEFAULT);

        return $next($request);
    }

    /** The language the address is written in, or the shop's own. */
    private static function fromAddress(Request $request): string
    {
        $first = mb_strtolower((string) $request->segment(1));

        return in_array($first, Locale::all(), true) ? $first : Locale::DEFAULT;
    }
}
