<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The books, the stock and the live photos are the owner's own: a manager
 * sees the orders and his own share, and nothing else. The desktop panel
 * draws the same line, so these two must never disagree.
 */
class OwnerOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return $next($request);
    }
}
