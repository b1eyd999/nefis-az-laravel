<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something that goes into every box — box paper, glue, print — bought in
 * packs: `pack_price` buys `pack_size` units (a 9.80 ₼ pack of 50 sheets),
 * `per_box` units go into one box, `stock` units are on hand.
 */
class Material extends Model
{
    protected $fillable = ['name', 'unit', 'pack_price', 'pack_size', 'per_box', 'stock', 'low_stock', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'pack_price' => 'float',
            'pack_size' => 'float',
            'per_box' => 'float',
            'stock' => 'float',
            'low_stock' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('per_box', '>', 0);
    }

    /** One unit: 9.80 ₼ / 50 sheets = 0.196 ₼ a sheet. */
    public function unitCost(): float
    {
        return $this->pack_size > 0 ? $this->pack_price / $this->pack_size : 0.0;
    }

    public function costPerBox(): float
    {
        return $this->unitCost() * $this->per_box;
    }

    /** How many more boxes the stock covers. */
    public function boxesLeft(): ?int
    {
        return $this->per_box > 0 ? (int) floor(max(0, $this->stock) / $this->per_box) : null;
    }

    public function isLow(): bool
    {
        return $this->stock <= 0 || ($this->low_stock !== null && $this->stock <= $this->low_stock);
    }

    /** What the materials of one finished box cost now. */
    public static function costOfOneBox(): float
    {
        return (float) static::used()->get()->sum(fn (Material $m) => $m->costPerBox());
    }
}
