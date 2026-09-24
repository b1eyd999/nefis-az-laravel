<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Setting;
use App\Support\ImageStore;
use App\Support\Telegram;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Paying by transfer. The customer is shown one of the owner's accounts —
 * card, M10 or bank — sends the money and uploads the receipt; the owner
 * checks it in the panel and confirms the order.
 */
class PaymentController extends Controller
{
    private function own(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 403);
    }

    public function show(Request $request, Order $order): View|RedirectResponse
    {
        $this->own($request, $order);

        if (! $order->awaitsPayment()) {
            return redirect(lroute('orders.index'));
        }

        // Nothing was set aside for this order yet, or that account is gone.
        if (! $order->paymentAccount) {
            $order->forceFill(['payment_account_id' => PaymentAccount::pick()?->id])->save();
        }

        return view('orders.pay', [
            'order' => $order->load('items', 'paymentAccount'),
            'offered' => PaymentAccount::offered(),
            'note' => Setting::get(Setting::PAYMENT_NOTE),
        ]);
    }

    /** The customer picks another way to pay; its account takes over. */
    public function method(Request $request, Order $order): RedirectResponse
    {
        $this->own($request, $order);
        abort_unless($order->awaitsPayment(), 404);

        $type = $request->validate(['type' => ['required', 'string', 'in:' . implode(',', array_keys(PaymentAccount::TYPES))]])['type'];
        $account = PaymentAccount::pick($type);
        abort_if($account === null, 404);

        $order->forceFill(['payment_account_id' => $account->id])->save();

        return redirect(lroute('orders.pay', $order));
    }

    public function receipt(Request $request, Order $order): RedirectResponse
    {
        $this->own($request, $order);
        abort_unless($order->awaitsPayment(), 404);

        $request->validate(
            ['receipt' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:8192']],
            ['receipt.required' => __('Ödənişin çekini (qəbzini) yükləyin.'), 'receipt.mimes' => __('Şəkil (PNG, JPG, WEBP) və ya PDF yükləyin.')]
        );

        $file = $request->file('receipt');
        $old = $order->payment_receipt;

        // A photographed receipt is re-encoded like any other upload; a PDF is kept as it is.
        $path = strtolower($file->getClientOriginalExtension()) === 'pdf'
            ? $file->store('receipts', 'public')
            : ImageStore::store($file, 'receipts', 'cek', 82, 1600)[0];

        $order->forceFill([
            'payment_receipt' => $path,
            'receipt_at' => now(),
            'status' => 'payment_check',
        ])->save();

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        defer(fn () => Telegram::receipt($order));

        return redirect(lroute('orders.index'))
            ->with('status', __('Çek göndərildi. Ödənişi yoxlayıb sifarişinizi təsdiqləyəcəyik.'));
    }
}
