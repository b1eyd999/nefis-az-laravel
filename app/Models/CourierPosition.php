<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reading from a courier's phone.
 *
 * Written only while he has sharing switched on, and thrown away after a few
 * hours: the owner needs to know where the box is this afternoon, not where
 * anybody drove last week.
 */
class CourierPosition extends Model
{
    /** The table only has the one timestamp; a position is never edited. */
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'lat', 'lng', 'accuracy'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'accuracy' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
