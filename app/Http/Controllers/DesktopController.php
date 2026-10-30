<?php

namespace App\Http\Controllers;

use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\CorporateRequestResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ReviewResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

/**
 * What the desktop app asks the shop every minute.
 *
 * The app is a window onto the admin panel, and it has one job the browser
 * cannot do: say out loud that an order has arrived while the owner is doing
 * something else. It cannot learn that from the page it is showing — the
 * sidebar's badge is drawn once, when the page loads — so it asks here.
 *
 * The numbers come from the panel's own badges rather than from a second set
 * of rules, because an app that announces three orders beside a sidebar
 * showing two is worse than an app that announces nothing.
 *
 * Nothing here identifies a customer. An order's number and its total are
 * enough for «a new order has come in», and a notification lands on a screen
 * that may not be the owner's alone.
 */
class DesktopController extends Controller
{
    public function state(): JsonResponse
    {
        /* `total` is worked out from the order's lines, not kept in a column,
           so the order itself has to be loaded — asking the table for a
           «total» field hands back nothing at all, quietly. */
        $newest = Order::query()
            ->with('items')
            ->whereIn('status', Order::WAITING)
            ->latest('id')
            ->first();

        return response()->json([
            'orders' => (int) OrderResource::getNavigationBadge(),
            /* The app remembers this number and speaks only when it grows.
               A count alone would stay silent when one order is dealt with
               and another arrives between two questions. */
            'newest_order' => (int) $newest?->id,
            /* A string, already rounded, because this hosting prints a
               float to its full binary length: 10.30 left the shop as
               10.300000000000007105427357601001858711242675781250. Money is
               not a thing to hand over and hope the reader rounds it back. */
            'newest_total' => number_format($newest ? $newest->total() : 0, 2, '.', ''),
            'messages' => (int) ContactMessageResource::getNavigationBadge(),
            'corporate' => (int) CorporateRequestResource::getNavigationBadge(),
            'reviews' => (int) ReviewResource::getNavigationBadge(),
        ], 200, [], JSON_UNESCAPED_UNICODE)
            ->header('Cache-Control', 'private, no-store');
    }
}
