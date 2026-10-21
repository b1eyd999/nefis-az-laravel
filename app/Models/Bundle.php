<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ready package, put together by the owner and hung on a banner.
 *
 * One design, and whatever goes with it, at one price. The customer never
 * assembles it: he presses «Sifariş et» on the banner, the design page opens
 * with everything already chosen and locked, and he adds his photograph.
 *
 * The parts are locked because the price belongs to the whole. Let the
 * wrapping be taken out and the package is no longer the package, but the
 * discount would still be sitting there.
 */
class Bundle extends Model
{
    protected $fillable = [
        'name', 'product_id', 'chocolate_id', 'wrapping_id',
        'letter_on', 'ar_on', 'price', 'image', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'letter_on' => 'boolean',
            'ar_on' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function chocolate(): BelongsTo
    {
        return $this->belongsTo(Chocolate::class);
    }

    public function wrapping(): BelongsTo
    {
        return $this->belongsTo(Wrapping::class);
    }

    /** Only a package whose design is still on sale can be ordered. */
    public function scopeOffered(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('product', fn (Builder $q) => $q->where('is_active', true));
    }

    /** The package as the customer is shown it, in his own language. */
    public function label(): string
    {
        return __($this->name);
    }

    public function picture(): ?string
    {
        return $this->image ? Media::url($this->image) : null;
    }

    /**
     * What the same things would cost bought one at a time.
     *
     * Printed beside the package price with a line through it, so the saving
     * is a figure the customer can see rather than a word he is asked to
     * believe. Null when there is nothing to compare — a package that saves
     * nothing says nothing.
     */
    public function fullPrice(): ?float
    {
        $sum = (float) ($this->product?->price ?? 0)
            /* A bar has no price column: what the customer pays is the
               shop price plus the owner's markup, worked out by the model.
               Reading ->price instead makes Eloquent look for a relationship
               of that name and throw. */
            + (float) ($this->chocolate?->price() ?? 0)
            + (float) ($this->wrapping?->price ?? 0)
            + ($this->letter_on ? (float) \App\Support\Letter::price() : 0)
            + ($this->ar_on ? (float) \App\Support\LiveMaterials::price() : 0);

        return $sum > (float) $this->price ? round($sum, 2) : null;
    }

    /** The address the banner's button points at. */
    public function url(): string
    {
        return lroute('products.customize', $this->product) . '?paket=' . $this->id;
    }

    /** What goes on the cart line, frozen the way the wrapping already is. */
    public function forCart(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'price' => (float) $this->price];
    }
}
