<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PhotoSlot extends Model
{
    protected $fillable = [
        'label',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'shape',
        'sort_order',
    ];

    public function slotable(): MorphTo
    {
        return $this->morphTo();
    }
}
