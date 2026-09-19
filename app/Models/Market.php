<?php

namespace App\Models;

use App\Support\ArazMarket;
use App\Support\Birmarket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop the chocolate bars are bought from; each is its own category in the
 * admin. A shop listed in IMPORTERS has its bars and prices read off its
 * website; any other shop's bars are added by hand.
 */
class Market extends Model
{
    /** Shops whose websites can be read, by their importer key. */
    public const IMPORTERS = [
        'arazmarket' => ArazMarket::class,
        'birmarket' => Birmarket::class,
    ];

    protected $fillable = ['name', 'slug', 'website', 'importer', 'sort_order'];

    protected static function booted(): void
    {
        // Its bars stay, just without a shop.
        static::deleting(fn (Market $market) => $market->chocolates()->update(['market_id' => null]));
    }

    public function chocolates(): HasMany
    {
        return $this->hasMany(Chocolate::class);
    }

    public function canSync(): bool
    {
        return isset(self::IMPORTERS[$this->importer]);
    }

    /**
     * Refreshes this shop's bars and prices from its website.
     *
     * @return array{found: int, created: int, updated: int, missing: int}
     */
    public function sync(): array
    {
        abort_unless($this->canSync(), 422, $this->name . ' saytından avtomatik oxunmur.');

        return (self::IMPORTERS[$this->importer])::sync();
    }

    /** The shop for an importer, made if the database does not have it yet. */
    public static function forImporter(string $importer, string $name, ?string $website = null): self
    {
        return static::firstOrCreate(['importer' => $importer], [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'website' => $website,
        ]);
    }
}
