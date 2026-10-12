<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Something the shop spends to make an order, bought in packs: `pack_price`
 * buys `pack_size` units (a 14 ₼ pack of 50 sheets), `per_box` units go into
 * one of whatever it is spent on, `stock` units are on hand.
 *
 * What it is spent on is the thing to read first. The box paper, the glue and
 * the print go into **every box**. The wrapping paper goes into **every box
 * somebody asked to have wrapped** — most are not — and a ribbon goes only
 * into the boxes whose wrap uses that very ribbon. Counting the wrapping per
 * box, as this table did at first, charged a sheet of it to every plain box
 * and put it in the price of one, which is why the owner said the wrapping has
 * to come off the shelf when somebody orders a wrap.
 */
class Material extends Model
{
    /** Spent on every box. */
    public const BOX = 'box';

    /** Spent on every box somebody asked to have wrapped. */
    public const WRAP = 'wrap';

    /** Spent only on the boxes whose wrap names this ribbon. */
    public const RIBBON = 'ribbon';

    /** @var array<string, string> How each is said on the owner's screens. */
    public const SPENT_ON = [
        self::BOX => 'Hər qutuya',
        self::WRAP => 'Hər bükməyə',
        self::RIBBON => 'Lent — yalnız onu seçən qablaşdırmalara',
    ];

    protected $fillable = ['name', 'unit', 'pack_price', 'pack_size', 'per_box', 'spent_on', 'stock', 'low_stock', 'counted_at', 'is_active', 'sort_order'];

    protected $attributes = ['spent_on' => self::BOX];

    protected function casts(): array
    {
        return [
            'pack_price' => 'float',
            'pack_size' => 'float',
            'per_box' => 'float',
            'stock' => 'float',
            'low_stock' => 'float',
            'counted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    /** The wraps that use this ribbon, if it is one. */
    public function wrappings(): HasMany
    {
        return $this->hasMany(Wrapping::class, 'ribbon_material_id');
    }

    /** Everything an order actually takes off the shelves. */
    public function scopeUsed(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('per_box', '>', 0);
    }

    /** Only what every box takes — what «bir qutunun materialı» is made of. */
    public function scopeEveryBox(Builder $query): Builder
    {
        return $query->used()->where('spent_on', self::BOX);
    }

    /** One unit: 14 ₼ / 50 sheets = 0.28 ₼ a sheet. */
    public function unitCost(): float
    {
        return $this->pack_size > 0 ? $this->pack_price / $this->pack_size : 0.0;
    }

    public function costPerBox(): float
    {
        return $this->unitCost() * $this->per_box;
    }

    /** How many more of its own thing — boxes, or wraps — the stock covers. */
    public function boxesLeft(): ?int
    {
        return $this->per_box > 0 ? (int) floor(max(0, $this->stock) / $this->per_box) : null;
    }

    /**
     * «qutu» or «bükmə» — what a unit of this material is spent on, for the
     * sentences the warehouse screens write about it. Azerbaijani endings
     * follow the vowel, so the three forms are given rather than glued on:
     * «qutuya» but «bükməyə», «qutuluq» but «bükməlik».
     */
    public function covers(): string
    {
        return $this->spent_on === self::BOX ? 'qutu' : 'bükmə';
    }

    /** «3 qutuya bəs edir» / «3 bükməyə bəs edir». */
    public function coversTo(): string
    {
        return $this->spent_on === self::BOX ? 'qutuya' : 'bükməyə';
    }

    /** «≈ 40 qutuluq» / «≈ 40 bükməlik». */
    public function coversWorth(): string
    {
        return $this->spent_on === self::BOX ? 'qutuluq' : 'bükməlik';
    }

    /** «Hər qutuya», «Hər bükməyə», «Lent — …». */
    public function spentOnLabel(): string
    {
        return self::SPENT_ON[$this->spent_on] ?? self::SPENT_ON[self::BOX];
    }

    /**
     * The shop has never written anything down about this one: nothing on the
     * shelf, and not one purchase or stocktake behind it.
     *
     * A roll of ribbon that has just appeared on the page is not «bitib» — it
     * is simply not counted yet, and saying the warehouse has run out of six
     * colours on the morning they appear would be six alarms the owner cannot
     * clear and would soon learn to ignore. Knowing what a roll costs does not
     * change that: the price is the recipe, the count is the shelf, and only
     * the second one can run out.
     *
     * One «Alış» or one «Sayım» ends it for good — including a «Sayım» of
     * nothing on an empty shelf, which writes no movement but does stamp
     * `counted_at`, because he did go and look.
     */
    public function isNew(): bool
    {
        return $this->counted_at === null && $this->stock <= 0;
    }

    /**
     * What the shop knows about first; what it has never counted, last.
     *
     * Every warehouse screen sorts by what is left, so a row sitting at zero
     * because nobody has counted it would otherwise stand above the paper that
     * is genuinely running out.
     */
    public function scopeKnownFirst(Builder $query): Builder
    {
        return $query->orderByRaw('CASE WHEN materials.counted_at IS NULL AND materials.stock <= 0 THEN 1 ELSE 0 END');
    }

    public function isLow(): bool
    {
        if ($this->isNew()) {
            return false;
        }

        return $this->stock <= 0 || ($this->low_stock !== null && $this->stock <= $this->low_stock);
    }

    /** What the materials of one plain, unwrapped box cost now. */
    public static function costOfOneBox(): float
    {
        return (float) static::everyBox()->get()->sum(fn (Material $m) => $m->costPerBox());
    }

    /** What wrapping a box adds on top, ribbon aside — it differs per wrap. */
    public static function costOfOneWrap(): float
    {
        return (float) static::used()->where('spent_on', self::WRAP)->get()
            ->sum(fn (Material $m) => $m->costPerBox());
    }

    /**
     * And what the ribbon adds: one figure while every roll costs the same,
     * the two ends of the range once they do not.
     *
     * @return array{0: float, 1: float}|null  null while none is priced
     */
    public static function costOfRibbon(): ?array
    {
        $costs = static::used()->where('spent_on', self::RIBBON)->get()
            ->map(fn (Material $m) => round($m->costPerBox(), 3))
            ->filter(fn (float $cost) => $cost > 0);

        return $costs->isEmpty() ? null : [$costs->min(), $costs->max()];
    }
}
