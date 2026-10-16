<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page answers at one address, not at every spelling of it.
 *
 * https://nefis.az// and https://nefis.az/// both returned 200 with a
 * byte-identical copy of the home page, and each named *itself* as its
 * canonical — so Google was offered three home pages, none of which pointed
 * at the real one. The same holds for /dizaynlar// and anything else a
 * doubled slash can be slipped into, and a crawler only needs to meet one
 * such link once to start asking for it for ever.
 *
 * Collapsing the slashes and sending the visitor on permanently settles it:
 * there is one address, every other spelling says so, and the duplicate
 * stops existing rather than being explained away in a canonical tag.
 */
class OneAddressPerPage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only GET and HEAD. A doubled slash on a form post is not worth
        // losing the body over; it is not something a crawler can index.
        if (! $request->isMethodCacheable()) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $tidy = preg_replace('#/{2,}#', '/', $path);

        if ($tidy === $path) {
            return $next($request);
        }

        /* Everything but the path is kept exactly as it was. The raw
           QUERY_STRING rather than getQueryString(), which sorts the
           parameters: a redirect should hand back the address it was given,
           not a tidied version of it. */
        $url = $request->getSchemeAndHttpHost() . $tidy;
        $query = (string) ($request->server('QUERY_STRING') ?? '');

        return redirect($query === '' ? $url : $url . '?' . $query, 301);
    }
}
