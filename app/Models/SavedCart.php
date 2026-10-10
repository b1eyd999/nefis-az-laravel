<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * What a signed-in customer has in his basket, kept out of the session.
 *
 * The session is still what every page reads; this is only the copy that
 * fills it again — on another telephone, or on the same one a week later.
 * It holds the basket as it stands, not a history of it.
 */
class SavedCart extends Model
{
    /** A basket cannot grow past this when two of them are put together. */
    public const MOST = 30;

    protected $fillable = ['user_id', 'items', 'rush'];

    protected function casts(): array
    {
        return ['items' => 'array', 'rush' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The lines in it, with anything that is not a line dropped. */
    public function lines(): array
    {
        return array_values(array_filter((array) ($this->items ?? []), 'is_array'));
    }

    /**
     * Put lines into somebody's kept basket, from outside his session.
     *
     * Added to what he already has rather than put in its place: he may have
     * been building something himself, and ten minutes of his work is worth
     * more than the tidiness of one basket. Each line is given a fresh id, so
     * taking one out of his basket cannot reach back into whatever it was
     * copied from. Returns how many lines went in.
     *
     * Nothing is sent and nothing is announced: the next time he opens the
     * shop, signed in on whichever telephone, the box is simply there.
     */
    public static function give(User $customer, array $lines): int
    {
        $saved = self::firstOrNew(['user_id' => $customer->id]);
        $out = $saved->exists ? $saved->lines() : [];
        $added = 0;

        foreach ($lines as $line) {
            if (! is_array($line) || count($out) >= self::MOST) {
                continue;
            }
            $line['id'] = (string) Str::uuid();
            $line['quantity'] = max(1, (int) ($line['quantity'] ?? 1));
            $out[] = $line;
            $added++;
        }

        $saved->items = $out;
        // Even an unchanged basket has to be stamped: a session notices a
        // basket given to it by what the basket holds.
        $saved->exists && ! $saved->isDirty() ? $saved->touch() : $saved->save();

        return $added;
    }

    /**
     * One line of the kind the design page would have made.
     *
     * Every key the basket reads is present and empty, so a line built here
     * behaves like one built by a customer: nothing downstream has to ask
     * whether a field exists before reading it.
     */
    public static function line(
        int $productId,
        int $quantity = 1,
        ?array $chocolate = null,
        ?array $wrapping = null,
        ?array $letter = null,
        ?array $ar = null,
    ): array {
        return [
            'product_id' => $productId,
            'quantity' => max(1, $quantity),
            'photo_paths' => [],
            'custom_texts' => [],
            'photo_labels' => [],
            'text_labels' => [],
            'photo_frames' => [],
            'star' => null,
            'spot' => null,
            'chocolate' => $chocolate,
            'wrapping' => $wrapping,
            'letter' => $letter,
            'ar' => $ar,
            'spotify' => null,
        ];
    }
}
