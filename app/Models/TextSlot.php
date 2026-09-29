<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TextSlot extends Model
{
    use \App\Models\Concerns\Translatable;

    public const KIND_TEXT = 'text';

    /** A duration typed as four digits and shown as mm:ss. */
    public const KIND_TIME = 'time';

    public const TIME_PATTERN = '/^\d{2}:[0-5]\d$/';

    /** How the letters are printed, whatever the customer typed. */
    public const CASES = ['none', 'upper', 'small'];

    protected $fillable = [
        'label',
        'i18n',
        'kind',
        'fixed',
        'x',
        'y',
        'max_width',
        'font_size',
        'color',
        'align',
        'font_family',
        'font_file',
        'placeholder',
        'default_value',
        'max_length',
        'max_lines',
        'sort_order',
        'rotation',
        'font_weight',
        // The typography of the caption, in the units the design was drawn in.
        'tracking',
        'line_height',
        'text_case',
        'scale_x',
        'scale_y',
        'baseline_shift',
        'stroke_color',
        'stroke_width',
        'shadow_color',
        'shadow_blur',
        'shadow_x',
        'shadow_y',
        'link_key',
    ];

    protected function casts(): array
    {
        return [
            'fixed' => 'boolean',
            'i18n' => 'array',
        ];
    }

    public function isTime(): bool
    {
        return $this->kind === self::KIND_TIME;
    }

    /**
     * How long the customer's text may be. Never shorter than the design's
     * own wording: a customer who leaves it as it is must not be turned away.
     */
    public function limit(): int
    {
        return max(1, (int) $this->max_length, mb_strlen((string) $this->default_value));
    }

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }
}
