<?php

namespace App\Models;

use App\Support\ChocolateBrand;
use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * A chocolate bar a customer can have put inside their box.
 *
 * The price a customer pays is the source price plus the markup: the bar's
 * own percentage if it has one, else the common one from the settings. The
 * source price is the shop's regular price, or its promotional price while
 * there is one if the owner chose to price from promotions.
 */
class Chocolate extends Model
{
    /*
     * Deleting a bar only marks it deleted: an imported bar has to stay known,
     * or the next import from its shop would bring it straight back.
     */
    use SoftDeletes;

    public const SOURCE_ARAZ = 'arazmarket';

    public const SOURCE_BIRMARKET = 'birmarket';

    public const SOURCE_BRAVO = 'bravo';

    protected $fillable = [
        'market_id', 'name', 'brand', 'weight_g', 'image', 'base_price', 'sale_price', 'sale_percent', 'markup_percent',
        'is_active', 'sort_order', 'source', 'source_id', 'source_url', 'barcode', 'seller', 'in_source', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'weight_g' => 'float',
            'base_price' => 'float',
            'sale_price' => 'float',
            'is_active' => 'boolean',
            'in_source' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // New bars (imported or added by hand) get their brand from the name.
        static::saving(function (Chocolate $chocolate) {
            $chocolate->brand = trim((string) $chocolate->brand) ?: ChocolateBrand::detect($chocolate->name);
        });

        static::forceDeleted(function (Chocolate $chocolate) {
            if ($chocolate->image) {
                Storage::disk('public')->delete($chocolate->image);
            }
        });
    }

    /** The shop the bar is bought from. */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /** The price the markup is added to. */
    public function costPrice(): float
    {
        $fromSale = Setting::get(Setting::CHOCOLATE_FROM_SALE) === '1';

        return $fromSale && $this->sale_price ? (float) $this->sale_price : (float) $this->base_price;
    }

    /** What the owner pays the shop for it today: the promotion while there is one. */
    public function shopPrice(): float
    {
        return (float) ($this->sale_price ?: $this->base_price);
    }

    public function markup(): int
    {
        return $this->markup_percent ?? (int) Setting::get(Setting::CHOCOLATE_MARKUP);
    }

    /** What the customer pays for the bar. */
    public function price(): float
    {
        return round($this->costPrice() * (1 + $this->markup() / 100), 2);
    }

    public function imageUrl(): ?string
    {
        return Media::url($this->image);
    }

    public function weightLabel(): ?string
    {
        return $this->weight_g ? rtrim(rtrim(number_format($this->weight_g, 1, '.', ''), '0'), '.') . ' q' : null;
    }

    /** What the customer page needs to show and price a bar. */
    public function toCustomer(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand ?: ChocolateBrand::OTHER,
            'weight' => $this->weightLabel(),
            'price' => $this->price(),
            'image' => $this->imageUrl(),
        ];
    }
}
