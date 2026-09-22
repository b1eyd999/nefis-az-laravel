<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A gift wrap: one paper and one ribbon. The customer's page wraps the box in
 * it on the mockups (public/js/wrap-render.js).
 */
class Wrapping extends Model
{
    public const SATIN = 'satin';

    public const TWINE = 'twine';

    public const NONE = 'none';

    public const RIBBONS = [
        self::SATIN => 'Atlas lent',
        self::TWINE => 'Kəndir (cut)',
        self::NONE => 'Lentsiz',
    ];

    protected $fillable = ['name', 'pattern', 'price', 'ribbon', 'ribbon_color', 'pattern_scale', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'pattern_scale' => 'float',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Wrapping $w) {
            if ($w->pattern) {
                Storage::disk('public')->delete($w->pattern);
            }
        });
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('price')->orderBy('sort_order')->orderBy('id');
    }

    /** What the customer's page needs to show it and wrap the box in it. */
    public function toCustomer(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'pattern' => Media::url($this->pattern),
            'ribbon' => $this->ribbon,
            'color' => $this->ribbon_color,
            'scale' => $this->pattern_scale,
        ];
    }
}
