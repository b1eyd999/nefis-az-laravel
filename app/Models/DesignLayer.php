<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of a box's artwork — a transparent image the owner placed in
 * the box editor, either under or over the customer's photo.
 */
class DesignLayer extends Model
{
    public const BELOW = 'below';

    public const ABOVE = 'above';

    protected $fillable = [
        'name',
        'image',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'opacity',
        'placement',
        'locked',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'locked' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
