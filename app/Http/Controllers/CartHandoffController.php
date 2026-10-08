<?php

namespace App\Http\Controllers;

use App\Models\CartHandoff;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

/**
 * The shop fills a basket for a customer, and hands it over by its address.
 *
 * The conversation usually starts in Instagram: the customer sends the
 * photograph and says what should be written on the box, and some of them
 * stop there rather than work the design page. The owner makes the box
 * himself — on the same page, with the same tools — and sends one link. What
 * the customer opens is his own basket, already full; everything after that
 * is the ordinary checkout.
 */
class CartHandoffController extends Controller
{
    /** Which basket the visitor was handed, so the order can be traced to it. */
    public const SESSION = 'cart_handoff';

    /** The owner turns what he has just built into a link. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $items = Cart::items();
        if ($items === []) {
            return back()->withErrors(['handoff' => __('Səbət boşdur.')]);
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:120']]);

        $made = CartHandoff::fromCart($items, Cart::rush(), $data['note'] ?? null, $request->user()->id);

        /* His own basket is emptied with it. He built it for somebody else,
           and leaving it behind is how the next customer's box ends up with
           a stranger's photograph in it. */
        Cart::clear();
        Cart::setRush(false);

        return back()->with('handoff.made', $made->id);
    }

    /**
     * The customer opens the address and finds the basket in his own hands.
     *
     * Whatever he had in it before is replaced: he was sent this one, and
     * two baskets mixed together is nobody's order.
     */
    public function open(string $token): RedirectResponse
    {
        $handoff = CartHandoff::where('token', $token)->first();

        if (! $handoff || ! $handoff->isOpen()) {
            return redirect(lroute('cart.index'))->withErrors([
                'handoff' => $handoff && $handoff->order_id
                    ? __('Bu səbət artıq sifariş olunub.')
                    : __('Bu səbətin vaxtı keçib. Bizə yazın, yenisini hazırlayaq.'),
            ]);
        }

        Cart::replace($handoff->items ?? []);
        Cart::setRush((bool) $handoff->rush);

        Session::put(self::SESSION, $handoff->id);

        if ($handoff->opened_at === null) {
            $handoff->forceFill(['opened_at' => now()])->save();
        }

        return redirect(lroute('cart.index'))->with('status', __('Sizin üçün hazırlanmış səbət açıldı. Yoxlayın və sifarişi tamamlayın.'));
    }
}
