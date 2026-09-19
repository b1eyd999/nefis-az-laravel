<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

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
        'box_color',
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

    protected static function booted(): void
    {
        // A box's uploads live in their own folder; nothing else uses them.
        static::deleted(fn (Product $product) => Storage::disk('public')->deleteDirectory($product->assetDirectory()));
    }

    /** Where the box editor keeps this product's uploaded artwork. */
    public function assetDirectory(): string
    {
        return 'boxes/' . $this->id;
    }

    public function catalogImage(): ?string
    {
        return $this->preview_image ?: $this->template_image;
    }

    /**
     * A product can be customised once it has artwork: layers built in the box
     * editor, or a single template image from before the editor existed.
     */
    public function isCustomizable(): bool
    {
        if (filled($this->template_image)) {
            return true;
        }

        return isset($this->attributes['layers_count'])
            ? $this->attributes['layers_count'] > 0
            : $this->layers()->exists();
    }

    public function isBox(): bool
    {
        return $this->relationLoaded('layers') ? $this->layers->isNotEmpty() : $this->layers()->exists();
    }

    public function categoryLabel(): ?string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(DesignLayer::class)->orderBy('sort_order');
    }

    /** Scenes picked for this product; none picked means every active one. */
    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class)->orderBy('scenes.sort_order')->orderBy('scenes.id');
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
