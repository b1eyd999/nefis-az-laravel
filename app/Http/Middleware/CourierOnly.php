<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The courier's own screen. His, and the owner's — the owner has to be able to
 * see what his courier is looking at. Nobody else, managers included: there is
 * nothing on it for them.
 */
class CourierOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->isCourier() || $user?->isAdmin(), 403);

        return $next($request);
    }
}
