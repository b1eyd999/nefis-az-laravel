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

    public const MAP = 'map';

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

    /** The printed looks of a street map, as public/js/street-map.js knows them. */
    public const MAP_STYLES = ['ink', 'paper', 'sea', 'colour'];

    /** How the map is bounded on the box. The house is the one shape only a map uses. */
    public const MAP_SHAPES = ['rectangle', 'ellipse', 'heart', 'home', 'full'];

    /** The mark on the spot itself. */
    public const MAP_MARKERS = ['none', 'pin', 'heart', 'star'];

    /**
     * Which switches the design offers the customer. The style is not among
     * them: it is how the box looks, and the owner sets it in the design.
     */
    public const MAP_CHOICES = ['zoom', 'pin', 'marker'];

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
        'map_style',
        'map_zoom',
        'map_marker',
        'map_choices',
        'map_pin',
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
            'sky_milky' => 'boolean', 'sky_lines' => 'boolean', 'sky_heart' => 'boolean',
            'map_pin' => 'boolean', 'map_zoom' => 'integer', 'i18n' => 'array'];
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

    /** A window the customer fills with a place on the map, not with a photograph. */
    public function isMap(): bool
    {
        return $this->fill === self::MAP;
    }

    /** Neither the sky nor a map: a window waiting for the customer's own photograph. */
    public function needsUpload(): bool
    {
        return ! $this->isSky() && ! $this->isMap();
    }

    /** The switches this design puts in front of the customer. */
    public function skyChoices(): array
    {
        $asked = array_filter(array_map('trim', explode(',', (string) $this->sky_choices)));

        return array_values(array_intersect($asked, self::SKY_CHOICES));
    }

    /** The switches the map design puts in front of the customer. */
    public function mapChoices(): array
    {
        $asked = array_filter(array_map('trim', explode(',', (string) $this->map_choices)));

        return array_values(array_intersect($asked, self::MAP_CHOICES));
    }

    /** How the map starts out before he touches anything. */
    public function mapDefaults(): array
    {
        return [
            'zoom' => (int) ($this->map_zoom ?: 15),
            'pin' => (bool) $this->map_pin,
            'marker' => in_array($this->map_marker, self::MAP_MARKERS, true) ? $this->map_marker : 'heart',
        ];
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
