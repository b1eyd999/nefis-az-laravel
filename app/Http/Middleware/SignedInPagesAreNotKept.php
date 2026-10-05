<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A page drawn for somebody who is signed in belongs to him, not to the
 * browser it was drawn in.
 *
 * It carries his name, what is in his basket, and a form token that is only
 * good for as long as his session is. The app's service worker keeps pages so
 * they open again with no signal — so it is told here, in a header, to leave
 * these alone. Two reasons, and both have bitten people:
 *
 *   • a phone gets lent, and the next person should not find the last one's
 *     page waiting on the shelf;
 *   • a token kept past its session is how pressing "Çıxış" ends in a page
 *     saying "Page Expired".
 */
class SignedInPagesAreNotKept
{
    public const HEADER = 'X-Nefis-Private';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user()) {
            $response->headers->set(self::HEADER, '1');
        }

        return $response;
    }
}
