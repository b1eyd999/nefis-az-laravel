<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shape drawn into a box's artwork — a band, a rule, a circle, a heart —
 * placed in the box editor either under or over the customer's photo.
 *
 * It is not an image: it is a handful of numbers, so it prints crisp at any
 * size and its colour can be changed without exporting anything.
 */
class DesignShape extends Model
{
    public const KINDS = ['rect', 'ellipse', 'line', 'triangle', 'heart', 'star'];

    protected $fillable = [
        'kind',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'fill',
        'stroke_color',
        'stroke_width',
        'radius',
        'opacity',
        'placement',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
