<?php

namespace App\Console\Commands;

use App\Mail\PaymentReminder;
use App\Models\Order;
use App\Models\Setting;
use App\Support\CustomerNotice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One letter to somebody who built a box, sent the order and then left the
 * payment page.
 *
 * Placing an order empties the basket, so a visitor who wandered off found
 * nothing anywhere and often believed the whole thing had failed. The
 * reminder says where his order is and takes him back to it — and it goes
 * out well before `orders:expire-unpaid` cancels it, so the letter is an
 * offer rather than a condolence.
 *
 * Exactly one per order, ever. A reminder sent every hour is a reason to
 * block the sender.
 */
class RemindUnpaidOrders extends Command
{
    protected $signature = 'orders:remind-unpaid {--hours= : how long an order may sit unpaid before the one reminder}';

    protected $description = 'Write once to customers who left the payment page, before the order expires';

    public function handle(): int
    {
        $after = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : (int) Setting::get(Setting::PAYMENT_REMIND_AFTER);

        if ($after <= 0) {
            $this->line('The reminder is switched off.');

            return self::SUCCESS;
        }

        if (! CustomerNotice::emailOn()) {
            $this->line('Customer e-mail is switched off.');

            return self::SUCCESS;
        }

        $window = max(1, (int) Setting::get(Setting::PAYMENT_WINDOW_HOURS));

        /* Not one whose letter would arrive after the order had already been
           cancelled: the reminder is only worth sending while there is still
           time to act on it. */
        if ($after >= $window) {
            $this->line('The reminder would arrive after the order expires (' . $after . 'h of ' . $window . 'h).');

            return self::SUCCESS;
        }

        $quiet = now()->subHours($after);

        $waiting = Order::where('status', 'awaiting_payment')
            ->whereNull('payment_confirmed_at')
            ->whereNull('payment_reminded_at')
            // A receipt on file means he has paid and is waiting for the owner.
            ->whereNull('payment_receipt')
            ->where('created_at', '<', $quiet)
            ->with('user')
            ->get()
            // Not one whose payment is at the bank this minute.
            ->reject(fn (Order $order) => $order->paymentInFlight());

        $sent = 0;
        foreach ($waiting as $order) {
            $to = trim((string) $order->user?->email);
            // Marked either way: an address that cannot be written to should
            // not be tried again every hour for the rest of the window.
            $order->forceFill(['payment_reminded_at' => now()])->save();

            if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $left = max(0, $window - (int) $order->created_at->diffInHours(now()));

            try {
                Mail::mailer(CustomerNotice::mailer())->to($to)
                    ->locale(CustomerNotice::locale($order))
                    ->send(new PaymentReminder($order, $left));
                $sent++;
            } catch (\Throwable $e) {
                Log::error('Ödəniş xatırlatması göndərilmədi: ' . $e->getMessage(), ['order' => $order->id]);
            }
        }

        $this->line($sent === 0
            ? 'Nobody to remind.'
            : 'Reminded ' . $sent . ' customer(s): #' . $waiting->pluck('id')->implode(', #'));

        return self::SUCCESS;
    }
}
