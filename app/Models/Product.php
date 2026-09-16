<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Product extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'sokolad' => 'Şokolad Dizaynları',
        'poster' => 'Posterlər',
        'love-is' => 'Love is...',
        'xerite' => 'Xəritə Posterləri',
        'spotify' => 'Spotify Posterləri',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'tag',
        'category',
        'preview_image',
        'price',
        'template_image',
        'overlay_image',
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
        'template_width',
        'template_height',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function catalogImage(): ?string
    {
        return $this->preview_image ?: $this->template_image;
    }

    public function isCustomizable(): bool
    {
        return filled($this->template_image);
    }

    public function categoryLabel(): ?string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function angles(): HasMany
    {
        return $this->hasMany(ProductAngle::class)->orderBy('sort_order');
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
