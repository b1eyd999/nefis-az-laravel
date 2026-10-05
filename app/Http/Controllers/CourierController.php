<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\CourierTrail;
use App\Support\Telegram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The courier's own screen — /kuryer.
 *
 * Deliberately not the admin panel and not the phone admin: a courier is not
 * staff, and `isStaff()` is the line every resource in both of those draws. He
 * gets his own few pages instead, and they show him one thing — the orders the
 * owner handed to him.
 *
 * Every page here checks the order is his. It is the first place in this shop
 * where who you are decides which rows you see, so it is done in plain sight,
 * one `abort_unless` at a time, rather than hidden in a trait.
 */
class CourierController extends Controller
{
    /** Orders off a courier's hands: nothing more to carry, nothing to see. */
    private const DONE = ['completed', 'cancelled', 'refunded'];

    public function index(Request $request): View
    {
        $me = $request->user();

        $mine = Order::query()
            ->where('courier_id', $me->id)
            ->whereNotIn('status', self::DONE)
            ->with(['user:id,name,phone', 'items:id,order_id,quantity,price,chocolate_price,wrapping_price,letter_price,ar_price'])
            ->orderByRaw('CASE WHEN delivery_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get();

        // What he dropped off today, so he can see his own day's work and
        // nothing older: a courier has no business with last week's addresses.
        $done = Order::query()
            ->where('courier_id', $me->id)
            ->where('status', 'completed')
            ->where('delivered_at', '>=', now()->startOfDay())
            ->orderByDesc('delivered_at')
            ->get(['id', 'delivery_address', 'delivered_at']);

        return view('courier.index', ['me' => $me, 'orders' => $mine, 'done' => $done]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->mine($request, $order);
        $order->load(['user:id,name,phone,email', 'items.product:id,name']);

        return view('courier.show', [
            'me' => $request->user(),
            'order' => $order,
            'collect' => $this->toCollect($order),
        ]);
    }

    /**
     * He has set off. The owner hears it at once — on his phone, in the group
     * he already watches — and the order carries the hour from then on. What
     * the customer is told stays the owner's own tap: he is the one who talks
     * to customers.
     */
    public function onTheWay(Request $request, Order $order): RedirectResponse
    {
        $this->mine($request, $order);
        $this->hisOwnHands($request);

        if (! $order->isOnTheWay()) {
            $order->forceFill(['on_the_way_at' => now()])->saveQuietly();
            Telegram::send('🚴 <b>Kuryer yola düşdü</b>'."\n"
                .'Sifariş #'.$order->id.' — '.e((string) $request->user()->name)."\n"
                .e((string) ($order->delivery_address ?: $order->deliverySummary() ?: '')));
        }

        return redirect()->route('courier.show', $order)
            ->with('courier.flash', 'Yola düşdüyünüz qeyd olundu.');
    }

    /** Handed over. The order's own save tells the customer and the books. */
    public function delivered(Request $request, Order $order): RedirectResponse
    {
        $this->mine($request, $order);
        $this->hisOwnHands($request);

        if (! in_array($order->status, self::DONE, true)) {
            // The model stamps the hour as it saves; it is read back for the
            // words on his own screen and for the owner's message.
            $order->forceFill(['status' => 'completed'])->save();
            $order->refresh();

            Telegram::send('✅ <b>Təhvil verildi</b>'."\n"
                .'Sifariş #'.$order->id.' — '.e((string) $request->user()->name)."\n"
                .$order->delivered_at?->format('d.m.Y H:i'));
        }

        return redirect()->route('courier.index')->with('courier.flash',
            'Sifariş #'.$order->id.' təhvil verildi — '.$order->delivered_at?->format('d.m.Y, H:i'));
    }

    /** The switch on his own screen: start sharing where he is, or stop. */
    public function share(Request $request): RedirectResponse
    {
        $me = $request->user();
        $on = $request->boolean('on');

        if (! $me->isCourier()) {
            /* The owner may look at this screen, but his own phone is not a
               courier's and must never end up on the map as one. Said out
               loud: a button that answers with the same screen and no word is
               indistinguishable from a broken one. */
            return back()->with('courier.flash', 'Lokasiyanı yalnız kuryer özü yandıra bilər.');
        }

        $on ? CourierTrail::start($me) : CourierTrail::stop($me);

        return back()->with('courier.flash', $on
            ? 'Lokasiya paylaşılır. Çatdırandan sonra söndürün.'
            : 'Lokasiya paylaşılmır.');
    }

    /**
     * A reading from his phone while the switch is on. Answers with whether
     * sharing is still on, so a page left open on a table learns that it has
     * lapsed and stops asking.
     */
    public function position(Request $request): JsonResponse
    {
        $me = $request->user();

        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'between:0,100000'],
        ]);

        if (! $me->isCourier()) {
            return response()->json(['sharing' => false], 200);
        }

        CourierTrail::record($me, (float) $data['lat'], (float) $data['lng'],
            isset($data['accuracy']) ? (int) round((float) $data['accuracy']) : null);

        return response()->json(['sharing' => $me->fresh()->isSharing()]);
    }

    /**
     * Where the couriers are, for the owner's map. Staff only, and the one
     * method here they can reach: the courier middleware guards the rest.
     */
    public function live(): JsonResponse
    {
        return response()->json(['couriers' => CourierTrail::map()]);
    }

    /**
     * The trip is the courier's to report.
     *
     * The owner is let onto these pages to see what his man is looking at,
     * and that is all it is for: if an admin could tap them, the shop would
     * record a handover that nobody made and announce the admin's own name as
     * the courier. He completes an order from the panel, under his own name.
     */
    private function hisOwnHands(Request $request): void
    {
        abort_unless($request->user()?->isCourier(), 403, 'Bu düymələr kuryerindir.');
    }

    /** The one rule of this whole screen: the order has to be his. */
    private function mine(Request $request, Order $order): void
    {
        $me = $request->user();

        // The owner can open a courier's page to see what the man is looking
        // at; a courier can only ever open his own.
        abort_unless($order->courier_id === $me->id || $me->isAdmin(), 404);
    }

    /** What he collects at the door, in manats. */
    private function toCollect(Order $order): float
    {
        return $order->isPaidFor()
            ? $order->outstanding()
            : round($order->total() + $order->outstanding(), 2);
    }
}
