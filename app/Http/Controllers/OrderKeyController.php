<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\OrderKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The door a letter's link opens.
 *
 * The signature is checked by the `signed` middleware before this runs, so
 * reaching here at all means the link was made by the shop and has not
 * expired. What is left is to let the customer into his own account and send
 * him to the page the letter was about.
 */
class OrderKeyController extends Controller
{
    public function open(Request $request, Order $order, string $door): RedirectResponse
    {
        abort_unless(in_array($door, OrderKey::DOORS, true), 404);

        $customer = $order->user;
        abort_unless($customer !== null, 404);

        /* Somebody else may be signed in on this telephone — the shop's own
           admin, or another member of the household. The letter went to this
           customer's address, so the link speaks for him. */
        if ($request->user()?->id !== $customer->id) {
            Auth::login($customer);
            $request->session()->regenerate();
        }

        $adjustment = $request->integer('adjustment') ?: null;

        return redirect(OrderKey::destination($order, $door, $adjustment));
    }
}
