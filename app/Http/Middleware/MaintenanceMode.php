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
    /** What stays open while the site is closed — live photos too: their QR codes are printed on boxes out there. */
    private const OPEN = ['admin', 'admin/*', 'login', 'logout', 'livewire/*', 'filament/*', 'up', 'canli/*'];

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
