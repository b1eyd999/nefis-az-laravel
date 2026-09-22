<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A gift-idea page (/hediyye/{slug}) written for one search: "ad günü
 * hədiyyəsi", "sevgiliyə hədiyyə"… Its words, questions and designs are the
 * owner's, edited in admin → Hədiyyə səhifələri.
 */
class GiftPage extends Model
{
    protected $fillable = [
        'slug', 'menu_label', 'emoji', 'title', 'meta_title', 'meta_description',
        'eyebrow', 'intro', 'body', 'faq', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function url(): string
    {
        return route('gifts.show', $this->slug);
    }

    public function label(): string
    {
        return trim(($this->emoji ? $this->emoji . ' ' : '') . $this->menu_label);
    }

    /**
     * Link words with the search phrase in them: "Ad günü hədiyyəsi", but
     * "Sevgiliyə hədiyyə" after a label that already says "to whom".
     */
    public function linkText(): string
    {
        return $this->menu_label . (preg_match('/[aə]$/u', $this->menu_label) ? ' hədiyyə' : ' hədiyyəsi');
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title . ' | Nefis';
    }

    public function metaDescription(): string
    {
        return $this->meta_description ?: Str::limit((string) $this->intro, 160);
    }

    /**
     * The designs this page offers: the owner's picks, or every design when
     * none are picked. Only ones a customer can open.
     *
     * @return Collection<int, Product>
     */
    public function shownProducts(): Collection
    {
        $picked = $this->products()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $products = $picked->isNotEmpty()
            ? $picked
            : Product::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return $products->filter->isCustomizable()->values();
    }

    /** The article, written in Markdown; any HTML typed into it is dropped. */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /** @return array<int, array{q: string, a: string}> */
    public function questions(): array
    {
        return array_values(array_filter((array) $this->faq, fn ($f) => is_array($f) && filled($f['q'] ?? null) && filled($f['a'] ?? null)));
    }
}
