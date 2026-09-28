<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Orders that went to the bank and never came back. Each one took its boxes'
 * paper, ribbon and card out of the stock figures the owner reorders from;
 * cancelling them puts that back (the order's own status hook does it) and
 * clears them from the customer's "unpaid order" bar.
 *
 * Runs from the scheduler — which on this hosting is a cPanel cron calling
 * `artisan schedule:run`; until that cron exists, this does nothing on its own.
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature = 'orders:expire-unpaid {--hours=24 : how long an order may wait for its card payment}';

    protected $description = 'Cancel orders left waiting for a card payment, so their materials go back to stock';

    public function handle(): int
    {
        $quiet = now()->subHours(max(1, (int) $this->option('hours')));

        $stale = Order::where('status', 'awaiting_payment')
            ->whereNull('payment_confirmed_at')
            // A receipt on file is the owner's to judge, not the clock's.
            ->whereNull('payment_receipt')
            ->where('created_at', '<', $quiet)
            // And nothing has happened to it since — an order the owner put
            // back to waiting this morning, so the customer can pay the rest,
            // is being worked on; cancelling it would take his materials back
            // and tell him by email that the order he is paying for is gone.
            ->where('updated_at', '<', $quiet)
            ->get()
            // Not one whose payment is at the bank this minute.
            ->reject(fn (Order $order) => $order->paymentInFlight());

        foreach ($stale as $order) {
            $order->update(['status' => 'cancelled']);
        }

        $this->line($stale->isEmpty() ? 'No unpaid orders to expire.' : 'Cancelled ' . $stale->count() . ' unpaid order(s): #' . $stale->pluck('id')->implode(', #'));

        return self::SUCCESS;
    }
}
