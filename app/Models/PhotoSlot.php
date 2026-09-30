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
    public const SKY_STYLES = ['night', 'ink', 'paper', 'navy', 'crimson', 'cream', 'sky', 'cosmos', 'moss'];

    /** How the sky is bounded on the box. */
    public const SKY_SHAPES = ['ellipse', 'heart', 'rectangle', 'full'];

    /** The ring around it: none, a thin circle, the graduated band, or a double rule. */
    public const SKY_RINGS = ['none', 'simple', 'degrees', 'double'];

    /**
     * Which switches the design offers the customer. The heart is not among
     * them: it is how the box looks, and the owner sets it in the design.
     */
    public const SKY_CHOICES = ['lines', 'labels', 'milky', 'time'];

    protected $fillable = [
        'label',
        'i18n',
        'fill',
        'sky_style',
        'sky_ring',
        'sky_ring_kind',
        'sky_choices',
        'sky_labels',
        'sky_milky',
        'sky_lines',
        'sky_heart',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'shape',
        'cutout',
        'locked',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['cutout' => 'boolean', 'locked' => 'boolean', 'sky_ring' => 'boolean', 'sky_labels' => 'boolean',
            'sky_milky' => 'boolean', 'sky_lines' => 'boolean', 'sky_heart' => 'boolean', 'i18n' => 'array'];
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

    /** The switches this design puts in front of the customer. */
    public function skyChoices(): array
    {
        $asked = array_filter(array_map('trim', explode(',', (string) $this->sky_choices)));

        return array_values(array_intersect($asked, self::SKY_CHOICES));
    }

    /** How the sky starts out before he touches anything. */
    public function skyDefaults(): array
    {
        return [
            'lines' => (bool) $this->sky_lines,
            'labels' => (bool) $this->sky_labels,
            'milky' => (bool) $this->sky_milky,
            'heart' => (bool) $this->sky_heart,
            // Not `time`: that key is the hour itself on the order line.
            'withTime' => false,
        ];
    }
}
