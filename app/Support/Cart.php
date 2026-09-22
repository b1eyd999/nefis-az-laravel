<?php

namespace App\Support;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class Cart
{
    protected const KEY = 'cart_items';

    /**
     * Each item: ['id' => string, 'product_id' => int, 'photo_paths' => string[], 'custom_texts' => string[], 'quantity' => int,
     *             'photo_labels' => string[], 'text_labels' => array{label: string, fixed: bool, repeat: bool}[],
     *             'chocolate' => ?array{id: int, name: string, price: float},
     *             'wrapping' => ?array{id: int, name: string, price: float},
     *             'letter' => ?array{text: ?string, photo: ?string, price: float}]
     * A Polaroid letter ordered on its own is a line with 'kind' => 'letter' and no product.
     */
    public static function items(): array
    {
        return Session::get(self::KEY, []);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::items(), 'quantity'));
    }

    public static function add(int $productId, array $photoPaths, array $customTexts, int $quantity = 1,
        array $photoLabels = [], array $textLabels = [], ?array $chocolate = null, ?array $wrapping = null, ?array $letter = null): void
    {
        $items = self::items();
        $items[] = [
            'id' => Str::uuid()->toString(),
            'product_id' => $productId,
            'photo_paths' => array_values($photoPaths),
            'custom_texts' => array_values($customTexts),
            'quantity' => max(1, $quantity),
            'photo_labels' => array_values($photoLabels),
            'text_labels' => array_values($textLabels),
            'chocolate' => $chocolate,
            'wrapping' => $wrapping,
            'letter' => $letter,
        ];
        Session::put(self::KEY, $items);
    }

    /** A Polaroid letter bought on its own, without a box. */
    public static function addLetter(array $letter, int $quantity = 1): void
    {
        $items = self::items();
        $items[] = [
            'id' => Str::uuid()->toString(),
            'kind' => 'letter',
            'product_id' => null,
            'photo_paths' => [],
            'custom_texts' => [],
            'quantity' => max(1, $quantity),
            'letter' => $letter,
        ];
        Session::put(self::KEY, $items);
    }

    public static function isLetter(array $item): bool
    {
        return ($item['kind'] ?? 'box') === 'letter';
    }

    /** One of a line: the box, the bar inside it, the paper around it and the letter in it. */
    public static function unitPrice(array $item, ?\App\Models\Product $product): float
    {
        return (float) ($product?->price ?? 0) + (float) ($item['chocolate']['price'] ?? 0)
            + (float) ($item['wrapping']['price'] ?? 0) + (float) ($item['letter']['price'] ?? 0);
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
