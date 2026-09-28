<?php

namespace App\Support;

use App\Models\OrderItem;
use Illuminate\Support\Facades\Cache;

/**
 * Which designs are actually selling.
 *
 * Counted from the order lines themselves, not from a flag someone has to
 * remember to set: the boxes sold in the last few months, cancelled orders
 * left out. The catalogue marks the top few with a flame so a visitor can
 * see at a glance what other people are buying.
 */
class BestSellers
{
    /** How many designs wear the flame. */
    public const TOP = 3;

    /** How far back a sale still counts. */
    public const DAYS = 90;

    /** Product ids, the best seller first. */
    public static function ids(): array
    {
        return Cache::remember('bestsellers', now()->addMinutes(30), function () {
            return OrderItem::query()
                ->selectRaw('product_id, SUM(quantity) as sold')
                ->whereNotNull('product_id')
                ->whereHas('order', fn ($q) => $q
                    ->where('status', '!=', 'cancelled')
                    ->where('created_at', '>=', now()->subDays(self::DAYS)))
                ->groupBy('product_id')
                ->orderByDesc('sold')
                ->limit(self::TOP)
                ->pluck('product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        });
    }

    public static function has(?int $productId): bool
    {
        return $productId !== null && in_array($productId, self::ids(), true);
    }

    public static function forget(): void
    {
        Cache::forget('bestsellers');
    }
}
