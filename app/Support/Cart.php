<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class Cart
{
    protected const KEY = 'cart_items';

    /**
     * Each item: ['id' => string, 'product_id' => int, 'photo_path' => string, 'custom_text' => ?string, 'quantity' => int]
     */
    public static function items(): array
    {
        return Session::get(self::KEY, []);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::items(), 'quantity'));
    }

    public static function add(int $productId, string $photoPath, ?string $customText, int $quantity = 1): void
    {
        $items = self::items();
        $items[] = [
            'id' => Str::uuid()->toString(),
            'product_id' => $productId,
            'photo_path' => $photoPath,
            'custom_text' => $customText,
            'quantity' => max(1, $quantity),
        ];
        Session::put(self::KEY, $items);
    }

    public static function remove(string $id): void
    {
        $items = array_values(array_filter(self::items(), fn ($item) => $item['id'] !== $id));
        Session::put(self::KEY, $items);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
    }
}
