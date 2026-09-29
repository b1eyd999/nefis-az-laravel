<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PhotoSlot extends Model
{
    use \App\Models\Concerns\Translatable;

    /** What falls into the window. */
    public const PHOTO = 'photo';

    public const SKY = 'sky';

    /** The printed looks of a star map, as public/js/star-map.js knows them. */
    public const SKY_STYLES = ['night', 'ink', 'paper', 'navy', 'crimson', 'cream', 'sky'];

    protected $fillable = [
        'label',
        'i18n',
        'fill',
        'sky_style',
        'sky_ring',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'shape',
        'cutout',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['cutout' => 'boolean', 'sky_ring' => 'boolean', 'i18n' => 'array'];
    }

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }

    /** A window the customer fills with the sky, not with a photograph. */
    public function isSky(): bool
    {
        return $this->fill === self::SKY;
    }
}
