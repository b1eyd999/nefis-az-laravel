<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAdjustment;
use Illuminate\Support\Facades\DB;

/**
 * Changing an order after it has been placed.
 *
 * Everything that edits an order's contents goes through here, for one
 * reason: the order's amount is not stored anywhere. `Order::total()` is
 * worked out from the lines every time it is asked, so the moment a line
 * changes the order is simply worth something else — and nothing would
 * remember that the customer had already paid the old figure.
 *
 * So a change is wrapped: the worth is read before and after, the materials
 * are brought in line with what the order now holds, and if the customer had
 * already paid, the difference is written down as its own row. That row is
 * what makes `Order::paidSoFar()` true afterwards.
 */
class OrderEditor
{
    /**
     * Makes a change and records what it did to the money.
     *
     * @param  callable():void  $edit  what to change about the order
     * @return OrderAdjustment|null  the money owed either way, if any
     */
    public static function change(Order $order, string $reason, callable $edit, ?int $by = null): ?OrderAdjustment
    {
        return DB::transaction(function () use ($order, $reason, $edit, $by) {
            $order->load('items');
            $before = $order->total();

            $edit();

            $order->load('items');
            $after = $order->total();

            // Stock follows the boxes, whether one was added or taken away.
            Accounting::resync($order);

            $difference = round($after - $before, 2);

            // Nothing moved, or the customer has not paid yet — in which case
            // his payment page still asks for the whole of the new total and
            // there is no difference to settle.
            if (abs($difference) < 0.01 || ! $order->isPaidFor()) {
                return null;
            }

            return $order->adjustments()->create([
                'kind' => $difference > 0 ? OrderAdjustment::CHARGE : OrderAdjustment::REFUND,
                'amount' => abs($difference),
                'reason' => $reason,
                'status' => OrderAdjustment::WAITING,
                'created_by' => $by ?? auth()->id(),
            ]);
        });
    }

    /**
     * The owner has the money in his hand (a transfer he has checked, or cash
     * at the door), or he has sent a refund back from the ePoint cabinet.
     */
    public static function settle(OrderAdjustment $adjustment, string $how = 'transfer'): void
    {
        $adjustment->forceFill([
            'status' => OrderAdjustment::PAID,
            'payment_method' => $adjustment->payment_method ?: $how,
            'payment_confirmed_at' => now(),
            'payment_started_at' => null,
        ])->save();
    }

    /**
     * A charge the customer is not going to pay: he changed his mind back, or
     * it was the owner's slip. Only a charge may be cancelled — money the shop
     * owes is settled by giving it back, so that `paidSoFar()` keeps telling
     * the truth about what the customer actually handed over.
     */
    public static function cancel(OrderAdjustment $adjustment): void
    {
        $adjustment->forceFill([
            'status' => OrderAdjustment::CANCELLED,
            'payment_started_at' => null,
        ])->save();
    }
}
