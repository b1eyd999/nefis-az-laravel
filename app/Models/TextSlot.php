<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TextSlot extends Model
{
    public const KIND_TEXT = 'text';

    /** A duration typed as four digits and shown as mm:ss. */
    public const KIND_TIME = 'time';

    public const TIME_PATTERN = '/^\d{2}:[0-5]\d$/';

    protected $fillable = [
        'label',
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
        ];
    }

    public function isTime(): bool
    {
        return $this->kind === self::KIND_TIME;
    }

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }
}
