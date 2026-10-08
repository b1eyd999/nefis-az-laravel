<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
