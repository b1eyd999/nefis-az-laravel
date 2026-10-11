<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAdjustment;
use Illuminate\Support\Facades\URL;

/**
 * A link in a letter that opens the page it is about.
 *
 * Everything a customer has to *do* after his order — pay for it, pay the
 * difference on a change, say how it went — lives behind `auth`. That was
 * fine while every customer had chosen a password. Since the checkout
 * started making the account for him (see App\Support\GuestCheckout), most
 * customers have one they were never told: a random forty characters.
 *
 * So the reminder letter said «Ödənişi tamamla», and the button opened a
 * sign-in form. The session it was created in had expired an hour before the
 * letter was even sent — 120 minutes against a reminder at three hours — and
 * a mail app opens links in a webview that carries no cookie at all. The one
 * letter written to rescue an order could not reach the order.
 *
 * These links carry a signature instead. Laravel checks it before anything
 * else runs, the customer is let into his own account, and he lands on the
 * page the letter was about. They are short-lived on purpose: a link that
 * opens an account is a key, and a key in an old e-mail should not still
 * turn.
 */
class OrderKey
{
    /** Where a key may let somebody in. Nothing else is reachable with one. */
    public const DOORS = ['pay', 'review', 'extra'];

    /** The page where the order is paid for. */
    public static function pay(Order $order, int $hours): string
    {
        return self::url($order, 'pay', $hours);
    }

    /** The page where he says how it went. */
    public static function review(Order $order, int $hours): string
    {
        return self::url($order, 'review', $hours);
    }

    /** The page where the difference on a changed order is paid. */
    public static function extra(OrderAdjustment $adjustment, int $hours): string
    {
        return self::url($adjustment->order, 'extra', $hours, ['adjustment' => $adjustment->id]);
    }

    /**
     * One signed link, good for this many hours.
     *
     * The destination is part of what is signed, so a link to the review
     * page cannot be edited into a link to anything else.
     */
    public static function url(Order $order, string $door, int $hours, array $extra = []): string
    {
        abort_unless(in_array($door, self::DOORS, true), 500);

        return URL::temporarySignedRoute('orders.key', now()->addHours(max(1, $hours)), [
            'order' => $order->id,
            'door' => $door,
        ] + $extra);
    }

    /**
     * Where a key of this kind lands, in the language the order was placed
     * in — a letter written in Russian must not end on an Azerbaijani page.
     */
    public static function destination(Order $order, string $door, ?int $adjustment = null): string
    {
        return match ($door) {
            'pay' => CustomerNotice::link($order),
            'review' => CustomerNotice::reviewLink($order),
            'extra' => $adjustment !== null && ($row = OrderAdjustment::find($adjustment))
                && $row->order_id === $order->id
                    ? CustomerNotice::adjustmentLink($row)
                    : CustomerNotice::link($order),
            default => CustomerNotice::link($order),
        };
    }
}
