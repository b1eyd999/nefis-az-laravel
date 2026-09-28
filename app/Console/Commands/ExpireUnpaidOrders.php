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
        $stale = Order::where('status', 'awaiting_payment')
            ->whereNull('payment_confirmed_at')
            ->where('created_at', '<', now()->subHours(max(1, (int) $this->option('hours'))))
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
