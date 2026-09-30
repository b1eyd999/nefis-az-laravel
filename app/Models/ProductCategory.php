<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shelf in the catalogue: "Şokolad Dizaynları", "Posterlər", and whatever
 * else the owner decides to put up.
 *
 * A design points at its shelf by the key it already stores in
 * `products.category`, not by an id, so renaming a shelf never touches a
 * design and deleting one cannot empty a product row.
 */
class ProductCategory extends Model
{
    use \App\Models\Concerns\Translatable;

    protected $fillable = ['slug', 'name', 'i18n', 'is_active', 'sort_order'];

    protected $casts = ['i18n' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    /** The shelves the catalogue shows, in the order the owner arranged them. */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function scopeInOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category', 'slug');
    }

    /**
     * The name a reader sees.
     *
     * The shelves the shop started with are already translated in
     * lang/ru.json and lang/en.json by their Azerbaijani wording; a shelf the
     * owner adds is translated in his own fields. Whichever exists is used.
     */
    public function label(): string
    {
        $written = (string) $this->tr('name');

        return $written === $this->name ? (string) __($this->name) : $written;
    }
}
