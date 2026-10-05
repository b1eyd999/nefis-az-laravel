<?php

namespace App\Support;

use App\Models\CourierPosition;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;

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
     * What the owner's map draws: every courier who is out — sharing now, or
     * holding an order that has not been delivered — with where he last was
     * and what he is carrying.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function map(): array
    {
        $couriers = User::query()
            ->where('role', User::COURIER)
            ->where(fn ($q) => $q->whereNotNull('sharing_until')
                ->orWhereHas('deliveries', fn ($d) => $d->whereNotIn('status', self::DONE)))
            ->orderBy('name')
            ->get();

        if ($couriers->isEmpty()) {
            return [];
        }

        $orders = Order::query()
            ->whereIn('courier_id', $couriers->modelKeys())
            ->whereNotIn('status', self::DONE)
            ->orderBy('delivery_date')
            ->orderBy('id')
            ->get(['id', 'courier_id', 'status', 'delivery_address', 'delivery_lat', 'delivery_lng',
                'delivery_slot', 'on_the_way_at', 'contact_phone'])
            ->groupBy('courier_id');

        $trail = CourierPosition::query()
            ->whereIn('user_id', $couriers->modelKeys())
            ->where('created_at', '>=', now()->subHours(self::KEEP_HOURS))
            ->orderBy('created_at')
            ->get(['user_id', 'lat', 'lng', 'accuracy', 'created_at'])
            ->groupBy('user_id');

        return $couriers->map(function (User $courier) use ($orders, $trail) {
            $points = $trail->get($courier->id) ?? collect();
            $last = $points->last();

            return [
                'id' => $courier->id,
                'name' => $courier->name,
                'phone' => $courier->phone,
                'sharing' => $courier->isSharing(),
                // The last reading, and when — "not sharing, last seen 12:40"
                // has to be tellable from "sharing, here".
                'at' => $last ? self::clock($last->created_at) : null,
                'lat' => $last?->lat,
                'lng' => $last?->lng,
                'accuracy' => $last?->accuracy,
                'trail' => $points->map(fn (CourierPosition $p) => [$p->lat, $p->lng])->values()->all(),
                'orders' => ($orders->get($courier->id) ?? collect())->map(fn (Order $o) => [
                    'id' => $o->id,
                    'status' => $o->statusLabel(),
                    'address' => $o->delivery_address,
                    'phone' => $o->contact_phone,
                    'slot' => $o->delivery_slot,
                    'lat' => $o->delivery_lat,
                    'lng' => $o->delivery_lng,
                    'on_the_way' => $o->isOnTheWay(),
                    'url' => route('filament.admin.resources.orders.edit', ['record' => $o->id]),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** The endings that take an order off a courier's hands. */
    private const DONE = ['completed', 'cancelled', 'refunded'];

    private static function clock(?Carbon $at): ?string
    {
        return $at?->timezone(config('app.timezone'))->format('H:i');
    }
}
