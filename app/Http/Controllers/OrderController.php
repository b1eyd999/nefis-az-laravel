<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            // The changes come along so the page can say what is still owed
            // either way without asking the database once per order.
            ->with(['items.product', 'items.livePhotos', 'adjustments'])
            ->latest()
            ->get();

        return view('orders.index', compact('orders'));
    }

    /**
     * The customer drops an order he never paid for.
     *
     * Cancelled, not deleted: its boxes took paper and ribbon out of the
     * stock the owner reorders from, and the status hook puts those back. The
     * row stays so both of them can see what happened — the same end the
     * nightly `orders:expire-unpaid` reaches, only sooner and by his own hand.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->mayBeDroppedBy($request->user()), 403);

        $order->update(['status' => 'cancelled']);

        return redirect(lroute('orders.index'))
            ->with('status', __('Sifariş ləğv edildi.'));
    }
}
