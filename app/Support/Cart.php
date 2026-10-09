<?php

namespace App\Support;

use App\Models\SavedCart;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class Cart
{
    protected const KEY = 'cart_items';

    /**
     * Each item: ['id' => string, 'product_id' => int, 'photo_paths' => string[], 'custom_texts' => string[], 'quantity' => int,
     *             'photo_labels' => string[], 'text_labels' => array{label: string, fixed: bool, repeat: bool}[],
     *             'photo_frames' => array{scale, rotate, flip, panX, panY, ratio, shape}|null[] (how each photo sat in its window),
     *             'star' => ?array{date, time, lat, lon, tz, place} (the night sky on the box),
     *             'chocolate' => ?array{id: int, name: string, price: float},
     *             'wrapping' => ?array{id: int, name: string, price: float},
     *             'letter' => ?array{text: ?string, photo: ?string, price: float},
     *             'ar' => ?array{video: string, image: ?string, mind: ?string, price: float},
     *             'spotify' => ?string (spotify:track:…)]
     * A Polaroid letter ordered on its own is a line with 'kind' => 'letter' and no product;
     * a live photo ordered on its own, 'kind' => 'live'.
     */
    /** Whether the customer asked for his order to be made before the others. */
    protected const RUSH = 'cart_rush';

    /** Whose kept basket this session has already been filled from. */
    protected const FILLED = 'cart_filled_for';

    public static function rush(): bool
    {
        // Asked for with the basket and kept with it, so it is read the
        // same way: whatever was kept is put back first.
        self::fillOnce();

        return (bool) Session::get(self::RUSH, false);
    }

    public static function setRush(bool $wanted): void
    {
        Session::put(self::RUSH, $wanted);
        self::keep();
    }

    /**
     * What is in the basket.
     *
     * The first time a signed-in customer reads it, whatever was kept for
     * him is put back in — he made it on another telephone, or last week on
     * this one. Every page reads the basket, so there is no moment to miss.
     */
    public static function items(): array
    {
        self::fillOnce();

        return self::raw();
    }

    /** The session's own copy, with nothing fetched and nothing filled. */
    protected static function raw(): array
    {
        return Session::get(self::KEY, []);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::items(), 'quantity'));
    }

    public static function add(int $productId, array $photoPaths, array $customTexts, int $quantity = 1,
        array $photoLabels = [], array $textLabels = [], ?array $chocolate = null, ?array $wrapping = null, ?array $letter = null, ?array $ar = null, ?string $spotify = null,
        array $photoFrames = [], ?array $star = null, ?array $spot = null): void
    {
        $items = self::raw();
        $items[] = [
            'id' => Str::uuid()->toString(),
            'product_id' => $productId,
            'photo_paths' => array_values($photoPaths),
            'custom_texts' => array_values($customTexts),
            'quantity' => max(1, $quantity),
            'photo_labels' => array_values($photoLabels),
            'text_labels' => array_values($textLabels),
            'photo_frames' => array_values($photoFrames),
            'star' => $star,
            'spot' => $spot,
            'chocolate' => $chocolate,
            'wrapping' => $wrapping,
            'letter' => $letter,
            'ar' => $ar,
            'spotify' => $spotify,
        ];
        Session::put(self::KEY, $items);
        self::keep();
    }

    /** A Polaroid letter bought on its own, without a box. */
    public static function addLetter(array $letter, int $quantity = 1): void
    {
        $items = self::raw();
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
        self::keep();
    }

    public static function isLetter(array $item): bool
    {
        return ($item['kind'] ?? 'box') === 'letter';
    }

    /** A live photo bought on its own: the customer's picture and video, no box. */
    public static function addLive(array $ar): void
    {
        $items = self::raw();
        $items[] = [
            'id' => Str::uuid()->toString(),
            'kind' => 'live',
            'product_id' => null,
            'photo_paths' => [],
            'custom_texts' => [],
            'quantity' => 1,
            'ar' => $ar,
        ];
        Session::put(self::KEY, $items);
        self::keep();
    }

    public static function isLive(array $item): bool
    {
        return ($item['kind'] ?? 'box') === 'live';
    }

    /** A line without a box: a letter or a live photo on its own. */
    public static function isExtra(array $item): bool
    {
        return self::isLetter($item) || self::isLive($item);
    }

    /** One of a line: the box, the bar inside it, the paper around it and the letter in it. */
    public static function unitPrice(array $item, ?\App\Models\Product $product): float
    {
        return (float) ($product?->price ?? 0) + (float) ($item['chocolate']['price'] ?? 0)
            + (float) ($item['wrapping']['price'] ?? 0) + (float) ($item['letter']['price'] ?? 0)
            + (float) ($item['ar']['price'] ?? 0);
    }

    public static function remove(string $id): void
    {
        $items = array_values(array_filter(self::raw(), fn ($item) => $item['id'] !== $id));
        Session::put(self::KEY, $items);
        // An empty basket hurries nothing.
        if ($items === []) {
            Session::forget(self::RUSH);
        }
        self::keep();
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
        Session::forget(self::RUSH);
        self::keep();
    }

    /**
     * Put a whole basket in place of whatever was there.
     *
     * Used when the shop has filled a basket for a customer and he opens it:
     * he was sent this one, and two baskets mixed together belong to nobody.
     * Each line is given a fresh id, so removing one here cannot reach back
     * into the basket it was copied from.
     */
    public static function replace(array $items): void
    {
        $out = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $item['id'] = Str::uuid()->toString();
            $item['quantity'] = max(1, (int) ($item['quantity'] ?? 1));
            $out[] = $item;
        }

        Session::put(self::KEY, $out);
        self::keep();
    }

    /**
     * The basket as it stands, kept where the session cannot lose it.
     *
     * Only for somebody who has signed in — there is nowhere to keep a
     * stranger's basket, and nothing to find it by again.
     */
    public static function keep(): void
    {
        $id = auth()->id();
        if (! $id) {
            return;
        }

        $saved = SavedCart::updateOrCreate(
            ['user_id' => $id],
            ['items' => self::raw(), 'rush' => (bool) Session::get(self::RUSH, false)],
        );

        // This session wrote it, so it has already got it: move the mark on,
        // or the next read would take the session's own basket for a new one
        // and put it back on top of itself.
        Session::put(self::FILLED, self::mark($id, $saved));
    }

    /** Whose basket, and what is in it — the two things a session must notice. */
    protected static function mark(int $id, ?SavedCart $saved): string
    {
        if (! $saved) {
            return $id . ':none';
        }

        return $id . ':' . crc32(json_encode($saved->lines()) . '|' . (int) $saved->rush);
    }

    /**
     * Once per session, put back what was kept for whoever has signed in.
     *
     * If he has built something since — a basket filled before signing in —
     * neither is thrown away: the two are put together, because losing ten
     * minutes of somebody's work is worse than a line he can delete. Lines
     * are matched by their id, so signing in again doubles nothing.
     */
    protected static function fillOnce(): void
    {
        $id = auth()->id();
        if (! $id) {
            return;
        }

        /* The mark is the customer and what his kept basket holds, not the
           customer alone. It used to be the customer alone, and that was
           enough while only he could change it. The shop can now put a basket
           into his, and with the old mark he would not have seen it until he
           signed in again — on a telephone where he was already signed in.
           The hour it was written will not do either: a basket given to
           somebody in the same second he last touched his own would carry
           the same stamp and be passed over. What it holds cannot. */
        $saved = SavedCart::where('user_id', $id)->first();
        $mark = self::mark($id, $saved);
        if (Session::get(self::FILLED) === $mark) {
            return;
        }

        // Written before the work, not after: everything below reads the
        // basket again, and this is what keeps that from looping.
        Session::put(self::FILLED, $mark);

        if (! $saved) {
            // Nothing kept yet; what he is carrying becomes what is kept.
            self::keep();

            return;
        }

        $here = self::raw();
        $seen = array_flip(array_column($here, 'id'));

        $out = $here;
        foreach ($saved->lines() as $line) {
            if (count($out) >= SavedCart::MOST) {
                break;
            }
            if (isset($line['id']) && isset($seen[$line['id']])) {
                continue;
            }
            $out[] = $line;
        }

        Session::put(self::KEY, $out);
        if ($saved->rush) {
            Session::put(self::RUSH, true);
        }

        self::keep();
    }
}
