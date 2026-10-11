<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A gift wrap: one paper and one ribbon. The customer's page wraps the box in
 * it on the mockups (public/js/wrap-render.js).
 */
class Wrapping extends Model
{
    use \App\Models\Concerns\Translatable;

    public const SATIN = 'satin';

    public const TWINE = 'twine';

    public const NONE = 'none';

    public const RIBBONS = [
        self::SATIN => 'Atlas lent',
        self::TWINE => 'Kəndir (cut)',
        self::NONE => 'Lentsiz',
    ];

    protected $fillable = [
        'i18n','name', 'pattern', 'price', 'ribbon', 'ribbon_color', 'ribbon_material_id',
        'pattern_scale', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
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

    /**
     * The roll on the shelf this wrap's ribbon is cut from.
     *
     * The link lives here rather than on the material because this is where
     * the owner thinks about it — a wrap is a paper and a ribbon — and because
     * one roll of red serves several wraps. Left empty, the wrap simply takes
     * no ribbon out of the warehouse.
     */
    public function ribbonMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'ribbon_material_id');
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
            // Whatever language the shop is being read in.
            // His own Russian if he wrote one, otherwise the shipped
            // translation of the Azerbaijani he typed.
            'name' => __($this->tr('name')),
            'price' => $this->price,
            'pattern' => Media::url($this->pattern),
            'ribbon' => $this->ribbon,
            'color' => $this->ribbon_color,
            'scale' => $this->pattern_scale,
        ];
    }
}
