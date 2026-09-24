<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * What the shop tells Google Analytics beyond "a page was opened": a box put
 * in the basket, a checkout started, an order placed and what it was worth.
 * Without those the numbers say how many people came, never which pages
 * bring orders.
 *
 * Events are put aside for one request (the page the customer lands on after
 * the action) and written out by the layout, so a refresh never counts an
 * order twice.
 */
class Analytics
{
    public const KEY = 'ga_events';

    public const CURRENCY = 'AZN';

    public static function on(): bool
    {
        return Seo::measurementId(Setting::get(Setting::SEO_ANALYTICS)) !== '' && ! app()->isLocal();
    }

    /** Remembers one event for the next page. */
    public static function event(string $name, array $params = []): void
    {
        if (! self::on()) {
            return;
        }

        $events = (array) session()->get(self::KEY, []);
        $events[] = ['name' => $name, 'params' => $params];
        session()->flash(self::KEY, $events);
    }

    /** The order as Google counts it: its worth, its delivery and its boxes. */
    public static function purchase(Order $order): void
    {
        $order->loadMissing('items');

        self::event('purchase', [
            'transaction_id' => (string) $order->id,
            'value' => round($order->total(), 2),
            'shipping' => round((float) ($order->delivery_price ?? 0), 2),
            'currency' => self::CURRENCY,
            'items' => $order->items->map(fn (OrderItem $item) => [
                'item_id' => $item->product_id ? 'design-' . $item->product_id : (Str::slug($item->product_name) ?: 'extra'),
                'item_name' => $item->product_name,
                'price' => round($item->unitPrice(), 2),
                'quantity' => (int) $item->quantity,
            ])->values()->all(),
        ]);
    }

    public static function addToCart(Product $product, float $price, int $quantity): void
    {
        self::event('add_to_cart', [
            'value' => round($price * $quantity, 2),
            'currency' => self::CURRENCY,
            'items' => [[
                'item_id' => 'design-' . $product->id,
                'item_name' => $product->name,
                'price' => round($price, 2),
                'quantity' => $quantity,
            ]],
        ]);
    }

    public static function beginCheckout(float $value): void
    {
        self::event('begin_checkout', ['value' => round($value, 2), 'currency' => self::CURRENCY]);
    }

    /** The waiting events, written as gtag calls; nothing when there are none. */
    public static function script(): HtmlString
    {
        $events = self::on() ? (array) session()->get(self::KEY, []) : [];

        if ($events === []) {
            return new HtmlString('');
        }

        $calls = '';
        foreach ($events as $event) {
            $params = json_encode(
                $event['params'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
            );
            $calls .= "  gtag('event', '" . addslashes($event['name']) . "', {$params});\n";
        }

        return new HtmlString("<script>\n  window.gtag = window.gtag || function(){(window.dataLayer = window.dataLayer || []).push(arguments);};\n{$calls}</script>");
    }
}
