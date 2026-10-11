<?php

namespace App\Support;

use App\Models\CourierPosition;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where the couriers are.
 *
 * A courier switches sharing on himself when he sets off — his own screen says
 * so in words while it is on — and it lapses by itself within the hour if his
 * phone stops reporting, because a page left open on a table is not a man on
 * the road. Nothing here follows anybody quietly: no sharing, no points.
 *
 * The points themselves are kept for a few hours and then thrown away. This is
 * for finding the box that is out now, not for keeping a record of anyone's
 * day.
 */
class CourierTrail
{
    /** How long one "on" lasts without the phone saying anything further. */
    public const MINUTES = 60;

    /** How much of the trail is kept behind him. */
    public const KEEP_HOURS = 6;

    /** He has set off: sharing is on, and his screen tells him so. */
    public static function start(User $courier): void
    {
        $courier->forceFill(['sharing_until' => now()->addMinutes(self::MINUTES)])->save();
    }

    /** He is done, or he changed his mind. The trail goes with it. */
    public static function stop(User $courier): void
    {
        $courier->forceFill(['sharing_until' => null])->save();
        $courier->positions()->delete();
    }

    /**
     * One reading from his phone. Kept only while sharing is on — a stale page
     * that carries on posting after he switched it off is ignored rather than
     * quietly turning it back on.
     */
    public static function record(User $courier, float $lat, float $lng, ?int $accuracy = null): ?CourierPosition
    {
        if (! $courier->isSharing()) {
            return null;
        }

        $courier->forceFill(['sharing_until' => now()->addMinutes(self::MINUTES)])->save();

        $position = $courier->positions()->create([
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'accuracy' => $accuracy !== null ? min(65535, max(0, $accuracy)) : null,
        ]);

        // Now and then rather than on every reading: a delete per ping would
        // be a write nobody asked for every fifteen seconds.
        if (random_int(1, 20) === 1) {
            self::prune();
        }

        return $position;
    }

    public static function prune(): void
    {
        CourierPosition::where('created_at', '<', now()->subHours(self::KEEP_HOURS))->delete();
    }

    /**
     * What the owner's map draws: every courier the shop has — his name, where
     * he last was, what he is carrying, and first of all where he is headed.
     *
     * Everyone is listed, not only the men who are moving. The owner opened
     * this page with nobody sharing and nobody carrying, and found an empty
     * box: a page that cannot tell «the shop has no couriers» from «no courier
     * is out right now» is no use to him. A man with nothing on his hands is
     * listed as free, with his name and his number still there to ring.
     *
     * Whoever is out comes first; inside each group the names stay in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function map(): array
    {
        $couriers = User::couriers();

        if ($couriers->isEmpty()) {
            return [];
        }

        $orders = Order::query()
            ->whereIn('courier_id', $couriers->modelKeys())
            ->whereNotIn('status', self::DONE)
            // The nearest day first, so the top of his list is the one he is
            // on — that is what «where is he headed» comes down to when nobody
            // has pressed «yola düşdüm» yet. Orders with no day queue behind.
            ->orderByRaw('CASE WHEN delivery_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get(['id', 'courier_id', 'status', 'delivery_address', 'delivery_lat', 'delivery_lng',
                'delivery_slot', 'delivery_date', 'delivery_type', 'delivery_name', 'metro_station',
                'postal_index', 'recipient_name', 'on_the_way_at', 'contact_phone'])
            ->groupBy('courier_id');

        $trail = CourierPosition::query()
            ->whereIn('user_id', $couriers->modelKeys())
            ->where('created_at', '>=', now()->subHours(self::KEEP_HOURS))
            ->orderBy('created_at')
            ->get(['user_id', 'lat', 'lng', 'accuracy', 'created_at'])
            ->groupBy('user_id');

        return $couriers
            ->map(function (User $courier) use ($orders, $trail) {
                $points = $trail->get($courier->id) ?? collect();
                $last = $points->last();
                $his = $orders->get($courier->id) ?? collect();

                /* The one he is on: the order he said he had set off with, or
                   else the first on his list. Everything else he is holding is
                   listed underneath it. */
                $heading = $his->first(fn (Order $o) => $o->isOnTheWay()) ?? $his->first();

                return [
                    'id' => $courier->id,
                    'name' => $courier->name,
                    'phone' => $courier->phone,
                    'sharing' => $courier->isSharing(),
                    // Out, in the only sense the owner cares about: either his
                    // phone is reporting or he has a box in his hands.
                    'out' => $courier->isSharing() || $his->isNotEmpty(),
                    // The last reading, and when — "not sharing, last seen 12:40"
                    // has to be tellable from "sharing, here".
                    'at' => $last ? self::clock($last->created_at) : null,
                    'lat' => $last?->lat,
                    'lng' => $last?->lng,
                    'accuracy' => $last?->accuracy,
                    'trail' => $points->map(fn (CourierPosition $p) => [$p->lat, $p->lng])->values()->all(),
                    'heading' => $heading ? self::door($heading, $last?->lat, $last?->lng) : null,
                    'orders' => self::load($his, $heading),
                ];
            })
            // Stable in PHP 8, so the names stay alphabetical within each group.
            ->sortBy(fn (array $c) => $c['out'] ? 0 : 1)
            ->values()
            ->all();
    }

    /**
     * Where a box has to end up, said the way the owner would say it.
     *
     * The address is the one the order itself prints — a metro station or a
     * postal index reads as the address for those, instead of a blank line —
     * and the kilometres are the straight line from wherever the courier last
     * was, which is enough to tell «he is nearly there» from «he has not left».
     *
     * @return array<string, mixed>
     */
    private static function door(Order $order, ?float $lat, ?float $lng): array
    {
        return [
            'id' => $order->id,
            'where' => $order->deliverySummary(),
            'how' => $order->delivery_name,
            'slot' => $order->delivery_slot,
            'day' => $order->delivery_date ? DeliveryTime::day($order->delivery_date) : null,
            'status' => $order->statusLabel(),
            'on_the_way' => $order->isOnTheWay(),
            'since' => self::clock($order->on_the_way_at),
            'phone' => $order->contact_phone,
            'lat' => $order->delivery_lat,
            'lng' => $order->delivery_lng,
            'km' => self::km($lat, $lng, $order->delivery_lat, $order->delivery_lng),
            'map' => $order->mapUrl(),
            'url' => route('filament.admin.resources.orders.edit', ['record' => $order->id]),
        ];
    }

    /**
     * Everything in his hands, with the one he is on marked as such.
     *
     * @param  Collection<int, Order>  $his
     * @return array<int, array<string, mixed>>
     */
    private static function load(Collection $his, ?Order $heading): array
    {
        return $his
            ->map(fn (Order $o) => self::door($o, null, null)
                + ['heading' => $heading !== null && $o->id === $heading->id])
            ->values()
            ->all();
    }

    /** Straight-line kilometres between two points, or nothing if one is missing. */
    private static function km(?float $aLat, ?float $aLng, ?float $bLat, ?float $bLng): ?float
    {
        if ($aLat === null || $aLng === null || $bLat === null || $bLng === null) {
            return null;
        }

        $dLat = deg2rad($bLat - $aLat);
        $dLng = deg2rad($bLng - $aLng);
        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($aLat)) * cos(deg2rad($bLat)) * sin($dLng / 2) ** 2;

        return round(2 * 6371.0 * asin(min(1.0, sqrt($h))), 1);
    }

    /** The endings that take an order off a courier's hands. */
    private const DONE = ['completed', 'cancelled', 'refunded'];

    private static function clock(?Carbon $at): ?string
    {
        return $at?->timezone(config('app.timezone'))->format('H:i');
    }
}
