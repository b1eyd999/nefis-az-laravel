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
        // Somebody else's order is not his to touch, and that is the only
        // thing worth a bare refusal.
        abort_unless($request->user() && $order->user_id === $request->user()->id, 403);

        /* Everything else is answered in words on his own page. He used to
           get «403 Forbidden» on a white screen: tapping the button twice,
           or coming back from the bank with the payment still in flight,
           both ended there — and a customer who has just tried to cancel an
           order and been shown an error code has no idea whether it was
           cancelled or not. */
        if ($order->status === 'cancelled') {
            return redirect(lroute('orders.index'))
                ->with('status', __('Bu sifariş artıq ləğv edilib.'));
        }

        if (! $order->mayBeDroppedBy($request->user())) {
            return redirect(lroute('orders.index'))->with('error', match (true) {
                $order->payment_confirmed_at !== null => __('Bu sifariş ödənilib — ləğv etmək üçün bizə yazın.'),
                filled($order->payment_receipt) => __('Çekiniz yoxlanılır. Ləğv etmək üçün bizə yazın.'),
                $order->paymentInFlight() => __('Ödəniş yoxlanılır. Bir neçə dəqiqədən sonra yenidən cəhd edin.'),
                default => __('Bu sifarişi artıq ləğv etmək olmur — bizə yazın, kömək edək.'),
            });
        }

        $order->update(['status' => 'cancelled']);

        return redirect(lroute('orders.index'))
            ->with('status', __('Sifariş ləğv edildi.'));
    }
}
