<?php

namespace App\Support;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The last few boxes that were actually ordered, for the small notice that
 * slides in at the corner of the shop.
 *
 * It is real proof, so it comes from real orders - but a customer never
 * agreed to have their name on the front page, so only the first name and
 * one initial are shown, and nothing else about them travels with it.
 */
class RecentSales
{
    /** How many notices the page cycles through. */
    public const KEEP = 8;

    /** How old a sale may be and still be worth showing. */
    public const DAYS = 21;

    public static function all(): array
    {
        $locale = Locale::current();

        return Cache::remember("recent-sales.$locale", now()->addMinutes(5), function () use ($locale) {
            return OrderItem::query()
                ->with(['order:id,user_id,recipient_name,created_at,status', 'order.user:id,name'])
                ->whereNotNull('product_id')
                ->whereHas('order', fn ($q) => $q
                    ->where('status', '!=', 'cancelled')
                    ->where('created_at', '>=', now()->subDays(self::DAYS)))
                ->latest('id')
                ->limit(self::KEEP)
                ->get()
                ->map(fn (OrderItem $item) => [
                    // The buyer's own name where there is one; a parcel sent to
                    // someone else falls back to the name on the parcel.
                    'who' => self::shortName($item->order?->user?->name ?: $item->order?->recipient_name),
                    'what' => $item->product_name,
                    'qty' => max(1, (int) $item->quantity),
                    'ago' => $item->order?->created_at?->locale($locale)->diffForHumans(),
                ])
                ->filter(fn (array $sale) => filled($sale['what']))
                ->values()
                ->all();
        });
    }

    /** "İlkin Kazımlı" -> "İlkin K." — enough to feel real, not enough to name someone. */
    public static function shortName(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return __('Müştəri');
        }

        $first = $parts[0];

        return count($parts) > 1
            ? $first . ' ' . Str::upper(Str::substr($parts[1], 0, 1)) . '.'
            : $first;
    }
}
