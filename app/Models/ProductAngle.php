<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProductAngle extends Model
{
    protected $fillable = [
        'product_id',
        'label',
        'template_image',
        'overlay_image',
        'template_width',
        'template_height',
        'background_image',
        'background_width',
        'background_height',
        'box_area_x',
        'box_area_y',
        'box_area_width',
        'box_area_height',
        'box_area_rotation',
        'content_x',
        'content_y',
        'content_width',
        'content_height',
        'content_rotation',
        'sort_order',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function photoSlots(): MorphMany
    {
        return $this->morphMany(PhotoSlot::class, 'slotable')->orderBy('sort_order');
    }

    public function textSlots(): MorphMany
    {
        return $this->morphMany(TextSlot::class, 'slotable')->orderBy('sort_order');
    }
}
