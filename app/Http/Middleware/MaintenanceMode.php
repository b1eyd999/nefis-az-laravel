<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The owner's "site closed for maintenance" switch (admin → Tənzimləmələr).
 *
 * Customers get a maintenance page; the owner and managers keep using the
 * site and the panel as usual, and can still reach the login page to get in.
 */
class MaintenanceMode
{
    /**
     * What stays open while the site is closed.
     *
     * Live photos: their QR codes are printed on boxes already out there.
     * The gateway's and Telegram's own addresses: a payment made a minute
     * before the shop closed is confirmed a minute after, and a 503 to that
     * message means the money arrives and the order stays unpaid for ever.
     *
     * Signing in and out is listed once for each language: only the
     * Azerbaijani pair was here, so a Russian customer pressing «Выход» while
     * the shop was closed was handed the maintenance page instead of being
     * signed out. The courier's screen stays open too — the deliveries of the
     * day do not stop because the shop is closed to new orders.
     */
    private const OPEN = ['admin', 'admin/*', 'livewire/*', 'filament/*', 'up',
        'login', 'logout', 'ru/login', 'ru/logout', 'en/login', 'en/logout',
        'kuryer', 'kuryer/*', 'canli/*', 'epoint/*', 'telegram/*',
        /* Apple and Google decide whether this domain is ours by fetching
           a file from here, and they do not come back often: a 503 served
           to one of them is a verification that failed, not a request that
           gets retried a minute later. */
        '.well-known/*',
        /* For the same reason as the two above: a 503 to a message from a
           customer is a question nobody will ever see — the only copy was in
           the request we refused — and Meta, after enough refusals, switches
           the hook off until somebody turns it back on by hand. */
        'whatsapp/hook'];

    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::get(Setting::MAINTENANCE) !== '1'
            || $request->user()?->isStaff()
            || $request->is(...self::OPEN)) {
            return $next($request);
        }

        return response()->view('maintenance', [
            'message' => Setting::get(Setting::MAINTENANCE_MESSAGE),
        ], 503)->header('Retry-After', '3600');
    }
}
