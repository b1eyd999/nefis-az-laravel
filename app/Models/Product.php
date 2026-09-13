<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tag',
        'price',
        'template_image',
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
        'photo_area_x',
        'photo_area_y',
        'photo_area_width',
        'photo_area_height',
        'photo_area_rotation',
        'photo_area_shape',
        'template_width',
        'template_height',
        'allow_text',
        'text_x',
        'text_y',
        'text_max_width',
        'text_font_size',
        'text_color',
        'text_align',
        'text_font_family',
        'text_font_file',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'allow_text' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function angles(): HasMany
    {
        return $this->hasMany(ProductAngle::class)->orderBy('sort_order');
    }
}
