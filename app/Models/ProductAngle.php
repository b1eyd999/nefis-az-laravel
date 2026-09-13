<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAngle extends Model
{
    protected $fillable = [
        'product_id',
        'label',
        'template_image',
        'template_width',
        'template_height',
        'photo_area_x',
        'photo_area_y',
        'photo_area_width',
        'photo_area_height',
        'photo_area_rotation',
        'allow_text',
        'text_x',
        'text_y',
        'text_max_width',
        'text_font_size',
        'text_color',
        'text_align',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allow_text' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
