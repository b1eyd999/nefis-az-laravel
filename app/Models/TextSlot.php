<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TextSlot extends Model
{
    protected $fillable = [
        'label',
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
        'sort_order',
    ];

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }
}
