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
        'photo_area_x',
        'photo_area_y',
        'photo_area_width',
        'photo_area_height',
        'photo_area_rotation',
        'template_width',
        'template_height',
        'allow_text',
        'text_x',
        'text_y',
        'text_max_width',
        'text_font_size',
        'text_color',
        'text_align',
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
}
