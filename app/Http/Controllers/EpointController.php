<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Epoint;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Paying by card.
 *
 * Three ways in. The customer asks to pay and is sent to the bank's page;
 * the bank sends him back to one of two addresses when he is done; and the
 * gateway itself, separately and server to server, tells us what happened.
 * Only that last one is believed — a customer can open a "success" address
 * by hand, but he cannot sign a message with our private key.
 */
class EpointController extends Controller
{
    /** The customer asks to pay his own order by card. */
    public function start(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless(Epoint::enabled(), 404);

        if (! $order->awaitsPayment()) {
            return redirect(lroute('orders.index'));
        }

        $url = Epoint::start(
            $order,
            route('epoint.done', ['order' => $order->id]),
            route('epoint.failed', ['order' => $order->id]),
        );

        if (! $url) {
            return redirect(lroute('orders.pay', $order))
                ->with('error', __('Kartla ödəniş indi işləmir. Bir az sonra yenidən yoxlayın və ya köçürmə ilə ödəyin.'));
        }

        return redirect()->away($url);
    }

    /**
     * What the gateway tells us, signed. This is the only word that moves an
     * order, and it may arrive more than once for the same payment.
     */
    public function result(Request $request): Response
    {
        $data = (string) $request->input('data');

        if (! Epoint::verify($data, $request->input('signature'))) {
            Log::warning('epoint: callback with a signature that is not ours');

            return response('bad signature', 400);
        }

        $body = Epoint::decode($data);
        $order = Order::find(Epoint::orderIdFrom($body['order_id'] ?? null));

        if (! $order) {
            Log::warning('epoint: callback for an order that is not here', ['order_id' => $body['order_id'] ?? null]);

            return response('unknown order', 200);      // nothing to retry: do not ask again
        }

        $paid = ($body['status'] ?? null) === Epoint::SUCCESS;
        $amount = (float) ($body['amount'] ?? 0);

        // The amount comes back with the answer; if it is not what the order
        // costs, something is wrong and the order stays unpaid until a human
        // has looked at it.
        if ($paid && abs($amount - $order->total()) > 0.01) {
            Log::error('epoint: paid amount does not match the order', [
                'order' => $order->id, 'paid' => $amount, 'expected' => $order->total(),
            ]);

            return response('amount mismatch', 200);
        }

        $order->forceFill([
            'payment_method' => 'card',
            'epoint_transaction' => $body['transaction'] ?? $order->epoint_transaction,
        ])->save();

        if (! $paid) {
            Log::info('epoint: payment not completed', [
                'order' => $order->id, 'status' => $body['status'] ?? null, 'message' => $body['message'] ?? null,
            ]);

            return response('ok', 200);
        }

        // Already settled: the gateway repeats itself, and the owner should
        // not hear about the same order twice.
        if ($order->payment_confirmed_at) {
            return response('ok', 200);
        }

        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        defer(fn () => Telegram::paid($order));

        return response('ok', 200);
    }

    /** Where the bank sends the customer back. Neither of these decides anything. */
    public function done(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return redirect(lroute('orders.index'))->with('status', $order->payment_confirmed_at
            ? __('Ödəniş qəbul edildi, sifarişiniz təsdiqləndi.')
            : __('Ödəniş qəbul edildi. Bankdan təsdiq gələn kimi sifariş təsdiqlənəcək.'));
    }

    public function failed(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        return redirect(lroute('orders.pay', $order))
            ->with('error', __('Ödəniş baş tutmadı. Yenidən cəhd edə və ya köçürmə ilə ödəyə bilərsiniz.'));
    }
}
