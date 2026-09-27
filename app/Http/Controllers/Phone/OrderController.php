<?php

namespace App\Http\Controllers\Phone;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\CustomerNotice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The orders, on a phone.
 *
 * What the owner does on his feet is narrow: see what is due today, open one,
 * push it one step forward. Everything that needs a desk — making an order,
 * deleting one, the money table — stays in the panel on the computer.
 *
 * Every change of status goes through the model's own save, never a quiet
 * update, because that save is what puts materials back in stock, writes to
 * the customer and calls the courier.
 */
class OrderController extends Controller
{
    /** What is still being worked on: the default list, newest promises first. */
    private const QUEUE = ['awaiting_payment', 'payment_check', 'pending', 'confirmed', 'ready'];

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $orders = Order::query()
            ->with([
                'user:id,name,phone',
                // unitPrice() adds these five up; asking for fewer costs a query per line.
                'items:id,order_id,product_id,quantity,price,chocolate_price,wrapping_price,letter_price,ar_price',
            ])
            ->withCount('items')
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($status === '', fn ($q) => $q->whereIn('status', self::QUEUE))
            // Orders with no day promised sort last rather than first, which is
            // what a NULL would otherwise do; written so both SQLite and MySQL
            // read it the same way.
            ->orderByRaw('CASE WHEN delivery_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('delivery_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('phone.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'counts' => $this->counts(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load(['user', 'paymentAccount', 'items.product', 'items.livePhotos']);

        return view('phone.orders.show', ['order' => $order]);
    }

    public function status(Request $request, Order $order): RedirectResponse
    {
        $wanted = (string) $request->input('status');
        abort_unless(array_key_exists($wanted, Order::STATUSES), 422);

        if ($wanted === $order->status) {
            return back();
        }

        // The model tells us afterwards whether the customer was written to.
        CustomerNotice::$sent = null;
        $order->forceFill(['status' => $wanted])->save();

        return back()->with('phone.flash', [
            'title' => 'Status dəyişdi',
            'body' => 'Sifariş #' . $order->id . ' — ' . Order::STATUSES[$wanted]
                . (CustomerNotice::$sent ? '. Müştəriyə e-poçt göndərildi.' : ''),
            'whatsapp' => CustomerNotice::whatsapp($order),
        ]);
    }

    public function confirmPayment(Order $order): RedirectResponse
    {
        if (! $order->awaitsPayment()) {
            return back();
        }

        CustomerNotice::$sent = null;
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        return back()->with('phone.flash', [
            'title' => 'Ödəniş təsdiqləndi',
            'body' => 'Sifariş #' . $order->id . ' — ' . Order::STATUSES['confirmed']
                . (CustomerNotice::$sent ? '. Müştəriyə e-poçt göndərildi.' : ''),
            'whatsapp' => CustomerNotice::whatsapp($order),
        ]);
    }

    /** How many sit under each chip, so a tap is never into an empty list. */
    private function counts(): array
    {
        $byStatus = Order::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $counts = ['' => 0, 'all' => (int) $byStatus->sum()];
        foreach (Order::STATUSES as $key => $label) {
            $counts[$key] = (int) ($byStatus[$key] ?? 0);
        }
        foreach (self::QUEUE as $key) {
            $counts[''] += (int) ($byStatus[$key] ?? 0);
        }

        return $counts;
    }
}
