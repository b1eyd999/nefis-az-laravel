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
    public function handle(Request $request, Closure $next, string $locale = Locale::DEFAULT): Response
    {
        app()->setLocale(in_array($locale, Locale::all(), true) ? $locale : Locale::DEFAULT);

        return $next($request);
    }
}
