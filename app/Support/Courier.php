<?php

namespace App\Support;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Handing a delivery to a courier, and telling the customer it has left.
 *
 * Both are the owner's decisions, and both are made from more than one screen
 * — the order's page, the list, the phone admin — so what they actually do
 * lives here once.
 */
class Courier
{
    /**
     * This order is his from now on.
     *
     * `courier_name` is written alongside the account on purpose: every screen
     * in the shop, and the Telegram group, already read that column, and
     * orders taken from the group have nothing else. Saved quietly — whose
     * delivery it is says nothing to the customer and must not set the status
     * machinery going.
     */
    public static function assign(Order $order, ?User $courier): void
    {
        $order->forceFill($courier ? [
            'courier_id' => $courier->id,
            'courier_name' => $courier->name,
            'courier_taken_at' => $order->courier_taken_at ?? now(),
        ] : [
            'courier_id' => null,
            'courier_name' => null,
            'courier_taken_at' => null,
            'on_the_way_at' => null,
        ])->saveQuietly();

        if (! $courier) {
            return;
        }

        // He hears it where he will see it: his own screen lists it at once,
        // and a letter reaches him if the shop has his address.
        self::tellCourier($order, $courier);

        Telegram::send('🚴 <b>Sifariş kuryerə verildi</b>'."\n"
            .'Sifariş #'.$order->id.' — '.e($courier->name)."\n"
            .e((string) ($order->delivery_address ?: $order->deliverySummary() ?: '')));
    }

    /**
     * The customer is told the box has left, in his own language, and the hour
     * is kept on the order so nobody tells him twice.
     *
     * Gives back what went wrong, or nothing — the owner is standing in front
     * of the screen and should hear it from it, not from a log.
     */
    public static function tellCustomer(Order $order): ?string
    {
        $failed = CustomerNotice::onTheWay($order);

        if ($failed === null && ! $order->isOnTheWay()) {
            $order->forceFill(['on_the_way_at' => now()])->saveQuietly();
        }

        return $failed;
    }

    /** A plain letter to the courier: the address, the hour, the way in. */
    private static function tellCourier(Order $order, User $courier): void
    {
        $to = trim((string) $courier->email);

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $lines = [
            'Sizə yeni sifariş verildi: #'.$order->id.'.',
            '',
            'Ünvan: '.($order->delivery_address ?: $order->deliverySummary() ?: '—'),
        ];
        if ($order->delivery_date) {
            $lines[] = 'Vaxt: '.DeliveryTime::day($order->delivery_date)
                .($order->delivery_slot ? ', '.$order->delivery_slot : '');
        }
        $lines[] = '';
        $lines[] = 'Sifarişləriniz: '.route('courier.index');

        try {
            Mail::mailer(CustomerNotice::mailer())->raw(implode("\n", $lines), fn ($m) => $m
                ->to($to)
                ->subject('Nefis.az — sifariş #'.$order->id)
                ->from(CustomerNotice::FROM, 'Nefis.az'));
        } catch (Throwable $e) {
            Log::error('Kuryerə məktub göndərilmədi: '.$e->getMessage(), ['order' => $order->id]);
        }
    }
}
