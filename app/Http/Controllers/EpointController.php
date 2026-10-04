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

        // One payment at a time: the bank's answer about the last one may
        // still be on its way, and a second page would be a second charge.
        if ($order->paymentInFlight()) {
            return redirect(lroute('orders.pay', $order));
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

        // Money owed over a change made after the order was paid for settles
        // on its own row, not on the order. It is looked up by the exact
        // reference, so it can never be confused with a payment for the order
        // itself — and the "already paid" guard below never sees it.
        $adjustment = \App\Models\OrderAdjustment::where('epoint_ref', (string) ($body['order_id'] ?? ''))->first();
        if ($adjustment) {
            return $this->adjustmentResult($adjustment, $body);
        }

        $order = Order::find(Epoint::orderIdFrom($body['order_id'] ?? null));

        if (! $order) {
            Log::warning('epoint: callback for an order that is not here', ['order_id' => $body['order_id'] ?? null]);

            return response('unknown order', 200);      // nothing to retry: do not ask again
        }

        $paid = ($body['status'] ?? null) === Epoint::SUCCESS;
        $amount = (float) ($body['amount'] ?? 0);
        $transaction = $body['transaction'] ?? null;
        $seen = $order->epoint_transaction;

        // What was asked of the bank when the customer left for it, not a
        // total worked out again now: the owner may have edited the order in
        // the meantime, and that must not turn a good payment into a wrong one.
        $expected = (float) ($order->payment_asked_for ?? $order->total());

        if ($paid && abs($amount - $expected) > 0.01) {
            Log::error('epoint: paid amount does not match the order', [
                'order' => $order->id, 'paid' => $amount, 'expected' => $expected,
            ]);
            // Money has been taken. Nobody reads a log file, so the owner is told.
            defer(fn () => Telegram::paymentProblem($order,
                'Ödənilən məbləğ uyğun gəlmir: ' . $amount . ' AZN, gözlənilən ' . $expected . ' AZN. Bank pulu götürüb.'));

            return response('amount mismatch', 200);
        }

        if (! $paid) {
            $order->forceFill([
                'payment_method' => 'card',
                'epoint_transaction' => $transaction ?: $seen,
                // The attempt is over: he may try again without waiting.
                'payment_started_at' => null,
            ])->save();

            Log::info('epoint: payment not completed', [
                'order' => $order->id, 'status' => $body['status'] ?? null, 'message' => $body['message'] ?? null,
            ]);

            return response('ok', 200);
        }

        // Already settled. The gateway does repeat itself, and that is fine —
        // but a DIFFERENT transaction id on a paid order is a second charge,
        // and the shop is the only one who can see it happen.
        if ($order->payment_confirmed_at) {
            if ($transaction && $seen && $transaction !== $seen) {
                Log::error('epoint: a second payment for an order already paid', [
                    'order' => $order->id, 'first' => $seen, 'second' => $transaction,
                ]);
                defer(fn () => Telegram::paymentProblem($order,
                    'Sifariş ikinci dəfə ödənilib. Əvvəlki əməliyyat: ' . $seen . ', yenisi: ' . $transaction
                    . '. Müştəriyə pulu qaytarmaq lazımdır.'));
            }

            return response('ok', 200);
        }

        $order->forceFill([
            'payment_method' => 'card',
            'epoint_transaction' => $transaction ?: $seen,
            'status' => 'confirmed',
            'payment_confirmed_at' => now(),
            'payment_started_at' => null,
        ])->save();

        defer(fn () => Telegram::paid($order));

        return response('ok', 200);
    }

    /**
     * The same reading of the bank's word, for money owed over a change.
     *
     * It is deliberately a copy of the order's own path rather than a shared
     * one: the order's is the path that takes the shop's money every day, and
     * it is not worth bending it to also mean something else.
     */
    private function adjustmentResult(\App\Models\OrderAdjustment $adjustment, array $body): Response
    {
        $paid = ($body['status'] ?? null) === Epoint::SUCCESS;
        $amount = (float) ($body['amount'] ?? 0);
        $transaction = $body['transaction'] ?? null;
        $seen = $adjustment->epoint_transaction;
        $expected = (float) ($adjustment->payment_asked_for ?? $adjustment->amount);

        if ($paid && abs($amount - $expected) > 0.01) {
            Log::error('epoint: paid amount does not match the surcharge', [
                'order' => $adjustment->order_id, 'adjustment' => $adjustment->id,
                'paid' => $amount, 'expected' => $expected,
            ]);
            defer(fn () => Telegram::paymentProblem($adjustment->order,
                'Əlavə ödənişin məbləği uyğun gəlmir: ' . $amount . ' AZN, gözlənilən ' . $expected
                . ' AZN. Bank pulu götürüb.'));

            return response('amount mismatch', 200);
        }

        if (! $paid) {
            $adjustment->forceFill([
                'payment_method' => 'card',
                'epoint_transaction' => $transaction ?: $seen,
                'payment_started_at' => null,
            ])->save();

            return response('ok', 200);
        }

        if ($adjustment->payment_confirmed_at) {
            if ($transaction && $seen && $transaction !== $seen) {
                Log::error('epoint: a second payment for a surcharge already paid', [
                    'order' => $adjustment->order_id, 'adjustment' => $adjustment->id,
                    'first' => $seen, 'second' => $transaction,
                ]);
                defer(fn () => Telegram::paymentProblem($adjustment->order,
                    'Əlavə ödəniş ikinci dəfə edilib. Əvvəlki əməliyyat: ' . $seen . ', yenisi: ' . $transaction
                    . '. Müştəriyə pulu qaytarmaq lazımdır.'));
            }

            return response('ok', 200);
        }

        $adjustment->forceFill([
            'payment_method' => 'card',
            'epoint_transaction' => $transaction ?: $seen,
            'status' => \App\Models\OrderAdjustment::PAID,
            'payment_confirmed_at' => now(),
            'payment_started_at' => null,
        ])->save();

        defer(fn () => Telegram::adjustmentPaid($adjustment));

        return response('ok', 200);
    }

    /** Where the bank sends the customer back. Neither of these decides anything. */
    public function done(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        // These two addresses carry no language of their own; the order remembers his.
        app()->setLocale(\App\Support\CustomerNotice::locale($order));

        return redirect(lroute('orders.index'))->with('status', $order->payment_confirmed_at
            ? __('Ödəniş qəbul edildi, sifarişiniz təsdiqləndi.')
            : __('Ödəniş qəbul edildi. Bankdan təsdiq gələn kimi sifariş təsdiqlənəcək.'));
    }

    public function failed(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        app()->setLocale(\App\Support\CustomerNotice::locale($order));

        return redirect(lroute('orders.pay', $order))
            ->with('error', __('Ödəniş baş tutmadı. Yenidən cəhd edə bilərsiniz.'));
    }
}
