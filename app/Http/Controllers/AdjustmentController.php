<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Support\Epoint;
use App\Support\ImageStore;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Paying for a change made to an order that was already paid for.
 *
 * It is a payment page like the order's own, with one difference that runs
 * through everything here: the order is not waiting for money and must not be
 * made to look as though it is. So nothing on this path asks `awaitsPayment()`
 * or touches the order's status — the gate is simply whether this one
 * adjustment is still open.
 */
class AdjustmentController extends Controller
{
    private function mine(Request $request, Order $order, OrderAdjustment $adjustment): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
        abort_unless($adjustment->order_id === $order->id, 404);
        // A refund is the shop's business to settle, not something the
        // customer pays; and a change already settled has no page.
        abort_unless($adjustment->isCharge() && $adjustment->isOpen(), 404);
    }

    public function show(Request $request, Order $order, OrderAdjustment $adjustment): View
    {
        $this->mine($request, $order, $adjustment);

        if (! $adjustment->payment_method && ! $order->paymentAccount) {
            $order->forceFill(['payment_account_id' => PaymentAccount::pick()?->id])->save();
        }

        return view('orders.extra', [
            'order' => $order->load('items'),
            'adjustment' => $adjustment,
            'account' => $order->paymentAccount,
            'offered' => PaymentAccount::offered(),
            'note' => Setting::get(Setting::PAYMENT_NOTE),
            'card' => Epoint::enabled(),
        ]);
    }

    /** To the bank's own page, for the difference only. */
    public function card(Request $request, Order $order, OrderAdjustment $adjustment): RedirectResponse
    {
        $this->mine($request, $order, $adjustment);
        abort_unless(Epoint::enabled(), 404);

        // One payment at a time, exactly as the order's own page does it.
        if ($adjustment->paymentInFlight()) {
            return redirect(lroute('orders.extra.show', [$order, $adjustment]));
        }

        $url = Epoint::startAdjustment(
            $adjustment,
            route('epoint.done', ['order' => $order->id]),
            route('epoint.failed', ['order' => $order->id]),
        );

        if (! $url) {
            return redirect(lroute('orders.extra.show', [$order, $adjustment]))
                ->with('error', __('Kartla ödəniş indi işləmir. Bir az sonra yenidən yoxlayın və ya köçürmə ilə ödəyin.'));
        }

        return redirect()->away($url);
    }

    /** Paid by transfer: the receipt goes to the owner to look at. */
    public function receipt(Request $request, Order $order, OrderAdjustment $adjustment): RedirectResponse
    {
        $this->mine($request, $order, $adjustment);

        $file = $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,pdf', 'max:8192'],
        ])['receipt'];

        $old = $adjustment->payment_receipt;
        /* ImageStore::put() has never existed, so every photographed receipt
           for a surcharge threw a fatal error and the money went uncollected;
           only a PDF ever got through. The order's own receipt path is the
           one that works, and it reads the kind off the bytes, so a phone
           that writes "CEK.PDF" or hands over a HEIC is handled too. */
        $path = $file->guessExtension() === 'pdf'
            ? $file->store('receipts', 'public')
            : ImageStore::store($file, 'receipts', 'cek', 82, 1600)[0];

        $adjustment->forceFill([
            'payment_method' => 'transfer',
            'payment_receipt' => $path,
            'receipt_at' => now(),
            'status' => OrderAdjustment::CHECK,
        ])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        defer(fn () => Telegram::paymentProblem($order,
            'Əlavə ödəniş üçün çek göndərildi: ' . \App\Support\Price::format((float) $adjustment->amount)
            . '. Yoxlamaq lazımdır.'));

        return redirect(lroute('orders.extra.show', [$order, $adjustment]))
            ->with('status', __('Çek göndərildi. Yoxlayıb təsdiqləyəcəyik.'));
    }
}
