<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The clock moved from UTC to Asia/Baku in this same release, and one column
 * cannot survive that on its own.
 *
 * `payment_started_at` is the only thing standing between a customer at
 * epoint.az and a second charge on his card: the pay page hides its button,
 * the unpaid-order bar stays down and the controller refuses a second gateway
 * page, all while that stamp is less than half an hour old. Read as Baku time,
 * a stamp written in UTC resolves four hours earlier than it really was — so
 * every payment in flight at the moment of the deploy would look long expired
 * and the shop would hand the customer the button again.
 *
 * Only payments still waiting for the bank's answer are touched; a finished
 * one is never looked at again. The write goes through the query builder so
 * `updated_at` is left alone — the expiry job reads it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $waiting = DB::table('orders')
            ->whereNotNull('payment_started_at')
            ->whereNull('payment_confirmed_at')
            ->get(['id', 'payment_started_at']);

        foreach ($waiting as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'payment_started_at' => \Illuminate\Support\Carbon::parse($order->payment_started_at)->addHours(4),
            ]);
        }
    }

    public function down(): void
    {
        $waiting = DB::table('orders')
            ->whereNotNull('payment_started_at')
            ->whereNull('payment_confirmed_at')
            ->get(['id', 'payment_started_at']);

        foreach ($waiting as $order) {
            DB::table('orders')->where('id', $order->id)->update([
                'payment_started_at' => \Illuminate\Support\Carbon::parse($order->payment_started_at)->subHours(4),
            ]);
        }
    }
};
