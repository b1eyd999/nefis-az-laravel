<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For the addresses the pages fetch data from, never the visitor.
 *
 * Laravel remembers the last address that was asked for as the page to come
 * back to, and `back()` sends the visitor there. It skips a request that says
 * it is a background one — and it asks after the controller has run, so
 * saying it here is in time.
 *
 * Without this, the chat in the corner asks for new messages every few
 * seconds, that address becomes "the page we were on", and the next `back()`
 * — deleting a line from the basket, a wrong password — drops the customer
 * onto a page of bare JSON.
 */
class AnswerIsNotAPage
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        return $next($request);
    }
}
