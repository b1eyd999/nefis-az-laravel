<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * What Samira is told about the shop.
 *
 * The rule this class keeps is that nothing in it can identify a customer:
 * a first name and what was in the box, never a telephone, an address, an
 * e-mail, a surname, a note, a caption or a photograph. The seven encrypted
 * columns on an order are never even read here — which is also why a wrong
 * APP_KEY cannot break the feed.
 *
 * "New" means the moment the owner would hear about it in Telegram: when
 * the money is confirmed, or — for an order the site never asked money for —
 * when it was written down. The same moment `Order::booted()` and
 * `CheckoutController` send the order card.
 */
final class SamiraFeed
{
    /** How many of each kind one answer carries; `more` says when there were others. */
    public const MOST = 50;

    /** The orders that are the shop's move, not the customer's. */
    public const HANDLING = ['payment_check', 'pending', 'confirmed', 'ready'];

    /** The statuses in the owner's own language, for Samira to say aloud. */
    public const STATUS_RU = [
        'awaiting_payment' => 'ждёт оплаты',
        'payment_check' => 'чек на проверке',
        'pending' => 'в ожидании',
        'confirmed' => 'подтверждён',
        'ready' => 'готов',
        'completed' => 'выполнен',
        'cancelled' => 'отменён',
        'refunded' => 'деньги возвращены',
    ];

    /** @return array<string, mixed> */
    public static function feed(CarbonInterface $since, CarbonInterface $now): array
    {
        $more = false;

        $paid = self::take(
            self::base()->whereNotNull('payment_confirmed_at')
                ->where('payment_confirmed_at', '>=', $since)
                ->where('payment_confirmed_at', '<', $now)
                ->orderBy('payment_confirmed_at')->orderBy('id'),
            $more,
        );

        /* An order the site asked no money for at all (no payment account,
           card payments off): the owner hears about it the moment it is
           written, so Samira does too. */
        $unasked = self::take(
            self::base()->whereNull('payment_confirmed_at')
                ->where('status', 'pending')
                ->where('created_at', '>=', $since)
                ->where('created_at', '<', $now)
                ->orderBy('created_at')->orderBy('id'),
            $more,
        );

        $orders = $paid->merge($unasked)
            ->sortBy(fn (Order $o) => ($o->payment_confirmed_at ?? $o->created_at)->getTimestamp())
            ->values()
            ->map(fn (Order $o) => self::order($o));

        // A customer says he has paid by transfer; the owner has to look.
        $receipts = self::take(
            self::base()->where('status', 'payment_check')
                ->whereNull('payment_confirmed_at')
                ->whereNotNull('receipt_at')
                ->where('receipt_at', '>=', $since)
                ->where('receipt_at', '<', $now)
                ->orderBy('receipt_at')->orderBy('id'),
            $more,
        )->map(fn (Order $o) => [
            'number' => $o->id,
            'time' => $o->receipt_at?->toIso8601String(),
            'unix' => $o->receipt_at?->getTimestamp(),
            'total' => $o->total(),
            'first_name' => self::firstName($o->user?->name),
            'admin_url' => self::adminUrl($o),
        ]);

        return [
            'site' => 'nefis.az',
            'now' => $now->getTimestamp(),
            'since' => $since->getTimestamp(),
            'timezone' => (string) config('app.timezone'),
            'currency' => 'AZN',
            'new_orders' => $orders->all(),
            'new_receipts' => $receipts->values()->all(),
            'today' => self::today(),
            'waiting' => self::waiting(),
            'more' => $more,
        ];
    }

    /**
     * One order, as Samira may know it.
     *
     * @return array<string, mixed>
     */
    public static function order(Order $order, bool $whole = false): array
    {
        $order->loadMissing(['items', 'user:id,name']);

        $row = [
            'number' => $order->id,
            'time' => ($order->payment_confirmed_at ?? $order->created_at)?->toIso8601String(),
            'unix' => ($order->payment_confirmed_at ?? $order->created_at)?->getTimestamp(),
            'paid' => $order->payment_confirmed_at !== null,
            'payment_method' => $order->payment_method,
            'total' => $order->total(),
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'status_ru' => self::STATUS_RU[$order->status] ?? $order->status,
            'first_name' => self::firstName($order->user?->name),
            'items' => $order->items->map(fn (OrderItem $item) => self::item($item))->values()->all(),
            'delivery' => [
                'type' => $order->delivery_type,
                'name' => $order->delivery_name,
                'date' => $order->delivery_date?->toDateString(),
                'slot' => $order->delivery_slot,
                'rush' => $order->isRush(),
                'free' => (bool) $order->free_delivery,
            ],
            'late' => self::isLate($order),
            'admin_url' => self::adminUrl($order),
        ];

        if (! $whole) {
            return $row;
        }

        $order->loadMissing('courier:id,name');

        return $row + [
            'ordered_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->payment_confirmed_at?->toIso8601String(),
            'receipt_at' => $order->receipt_at?->toIso8601String(),
            'courier_taken_at' => $order->courier_taken_at?->toIso8601String(),
            'on_the_way_at' => $order->on_the_way_at?->toIso8601String(),
            'delivered_at' => $order->delivered_at?->toIso8601String(),
            // Staff, not a customer — and still only the first name.
            'courier_first_name' => self::firstName($order->courier?->name),
            'promo_code' => $order->promo_code,
            'discount' => $order->discountOff(),
            'delivery_charged' => $order->deliveryCharged(),
            'rush_fee' => (float) ($order->rush_fee ?? 0),
            'outstanding' => $order->outstanding(),
            'owed_back' => $order->owedBack(),
        ];
    }

    /** @return array<string, mixed> */
    private static function item(OrderItem $item): array
    {
        return [
            'name' => $item->product_name ?: $item->title(),
            'quantity' => (int) $item->quantity,
            'sum' => round($item->unitPrice() * (int) $item->quantity, 2),
            'package' => $item->bundle_name ?: null,
            'chocolate' => $item->chocolate_name ?: null,
            'wrap' => $item->wrapping_name ?: null,
            'letter' => $item->hasLetter(),
            'live_photo' => (float) ($item->ar_price ?? 0) > 0,
        ];
    }

    /** @return array<string, mixed> */
    private static function today(): array
    {
        $paid = Order::query()->with('items')
            ->whereNotNull('payment_confirmed_at')
            ->whereDate('payment_confirmed_at', today())
            ->whereNotIn('status', Order::OFF_THE_BOOKS)
            ->get();

        $unpaid = Order::query()->with('items')
            ->whereIn('status', ['awaiting_payment', 'payment_check'])
            ->whereNull('payment_confirmed_at')
            ->get();

        return [
            'date' => today()->toDateString(),
            'orders' => $paid->count(),
            'sum' => round((float) $paid->sum(fn (Order $o) => $o->total()), 2),
            'deliveries' => Order::query()->whereIn('status', self::HANDLING)
                ->whereDate('delivery_date', today())->count(),
            'awaiting_payment' => $unpaid->count(),
            'awaiting_payment_sum' => round((float) $unpaid->sum(fn (Order $o) => $o->total()), 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function waiting(): array
    {
        $counts = Order::query()->whereIn('status', self::HANDLING)
            ->selectRaw('status, count(*) as n')->groupBy('status')
            ->pluck('n', 'status');

        $byStatus = [];
        foreach (self::HANDLING as $status) {
            $byStatus[$status] = (int) ($counts[$status] ?? 0);
        }

        $orders = self::base()->whereIn('status', self::HANDLING)
            ->orderByRaw('delivery_date is null')->orderBy('delivery_date')->orderBy('id')
            ->limit(self::MOST)->get();

        return [
            'count' => array_sum($byStatus),
            'by_status' => $byStatus,
            'late' => Order::query()->whereIn('status', self::HANDLING)
                ->whereNotNull('delivery_date')->whereDate('delivery_date', '<', today())->count(),
            // Changes to paid orders whose transfer receipt is waiting to be looked at.
            'adjustments_to_check' => OrderAdjustment::query()->where('status', OrderAdjustment::CHECK)->count(),
            'orders' => $orders->map(fn (Order $o) => [
                'number' => $o->id,
                'status' => $o->status,
                'status_ru' => self::STATUS_RU[$o->status] ?? $o->status,
                'first_name' => self::firstName($o->user?->name),
                'total' => $o->total(),
                'delivery_date' => $o->delivery_date?->toDateString(),
                'delivery_slot' => $o->delivery_slot,
                'late' => self::isLate($o),
                'admin_url' => self::adminUrl($o),
            ])->all(),
        ];
    }

    private static function base(): Builder
    {
        return Order::query()->with(['items', 'user:id,name']);
    }

    /** At most MOST rows; one more is asked for only to know whether there were others. */
    private static function take(Builder $query, bool &$more): Collection
    {
        $rows = $query->limit(self::MOST + 1)->get();

        if ($rows->count() > self::MOST) {
            $more = true;
        }

        return $rows->take(self::MOST);
    }

    private static function isLate(Order $order): bool
    {
        return in_array($order->status, self::HANDLING, true)
            && $order->delivery_date !== null
            && $order->delivery_date->lt(today());
    }

    /**
     * The first word of the name on the account, and nothing after it.
     *
     * Never `recipient_name`: that is the person the box goes to, it is
     * encrypted for a reason, and it is often the whole name.
     */
    public static function firstName(?string $name): ?string
    {
        $first = preg_split('/\s+/u', trim((string) $name), 2)[0] ?? '';

        return $first === '' ? null : mb_substr($first, 0, 40);
    }

    private static function adminUrl(Order $order): string
    {
        return url('/admin/orders/' . $order->id . '/edit');
    }
}
