<?php

namespace App\Http\Middleware;

use App\Support\SamiraKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The door to Samira's feed: the key in an `Authorization: Bearer` header,
 * or a 401 — whatever else the request carries.
 *
 * No cookie, no session, no query-string key (an address ends up in logs and
 * browser histories; a header does not). And nothing here may be kept by
 * anybody on the way — a proxy, a browser, the hosting's own cache — because
 * the answer is the shop's day, and changes every minute.
 */
class SamiraOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $presented = $request->bearerToken();

        if (! SamiraKey::matches($presented)) {
            /* Which of the two it was is no secret, and it is the one thing
               that tells, from outside, whether the hosting passes the
               Authorization header through to PHP at all. */
            return self::private(new JsonResponse(
                ['error' => $presented === null || $presented === '' ? 'no_key' : 'bad_key'],
                401,
                ['WWW-Authenticate' => 'Bearer realm="samira"'],
            ));
        }

        SamiraKey::seen($request);

        return self::private($next($request));
    }

    public static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Vary', 'Authorization');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        // Read by a program on the owner's computer, never by a web page.
        $response->headers->remove('Access-Control-Allow-Origin');

        return $response;
    }
}
