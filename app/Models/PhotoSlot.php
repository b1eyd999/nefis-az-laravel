<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PhotoSlot extends Model
{
    use \App\Models\Concerns\Translatable;

    protected $fillable = [
        'label',
        'i18n',
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
        return ['cutout' => 'boolean', 'i18n' => 'array'];
    }

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }
}
