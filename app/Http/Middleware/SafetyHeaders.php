<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The handful of headers that tell a browser how to treat this site.
 *
 * The one that matters is the first. Without it, a phone told to open
 * "nefis.az" asks for it over plain http and only then gets redirected —
 * and on somebody else's wifi that first request is readable, cookies and
 * all. Strict-Transport-Security makes the browser remember, for a year,
 * that this host is https-only and skip that first hop for good.
 *
 * The others close smaller doors: guessing a file's type against what the
 * server said, putting the shop in a frame on another site, and handing the
 * full address of a page to whoever is linked from it.
 */
class SafetyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /* Only over https, and never from a local run: a browser that is told
           this once will refuse plain http to the host for a year, which on a
           development machine means locking yourself out of http://localhost. */
        if ($request->secure() && app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // The shop is never meant to be shown inside another site's frame.
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        /* Another site gets to know a visitor came from nefis.az, but not
           which page he was on — an order page's address is nobody's business. */
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        /* The camera is allowed for this site's own pages and nobody else's:
           the live photo page (/canli/…) opens the phone's camera through the
           browser (getUserMedia) to find the picture on the box. With camera=()
           the browser refuses before it even asks, and the visitor sees
           "The camera did not open". The microphone stays off. */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(self), payment=(), usb=(), interest-cohort=()'
        );

        return $response;
    }
}
