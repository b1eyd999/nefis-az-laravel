<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A gift-idea page written for one search: "ad günü hədiyyəsi", "подарок на
 * день рождения"… Azerbaijani pages live at /hediyye/{slug}, their Russian
 * versions at /podarki/{slug}; a Russian page points at its Azerbaijani twin
 * with `alt_of`, which pairs them for search engines and lends it the
 * designs. The owner writes them all in admin → Hədiyyə səhifələri.
 */
class GiftPage extends Model
{
    public const LOCALES = ['az' => 'Azərbaycanca', 'ru' => 'Rusca'];

    protected $fillable = [
        'slug', 'locale', 'alt_of', 'menu_label', 'link_text', 'emoji', 'title',
        'meta_title', 'meta_description', 'eyebrow', 'intro', 'body', 'faq',
        'is_active', 'sort_order',
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

    /** The Azerbaijani page this one is a translation of. */
    public function alt(): BelongsTo
    {
        return $this->belongsTo(GiftPage::class, 'alt_of');
    }

    /** The translations pointing at this page. */
    public function alternates(): HasMany
    {
        return $this->hasMany(GiftPage::class, 'alt_of');
    }

    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeInLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    public function url(): string
    {
        return route($this->locale === 'ru' ? 'gifts.show.ru' : 'gifts.show', $this->slug);
    }

    public static function hubUrl(string $locale): string
    {
        return route($locale === 'ru' ? 'gifts.index.ru' : 'gifts.index');
    }

    public function label(): string
    {
        return trim(($this->emoji ? $this->emoji . ' ' : '') . $this->menu_label);
    }

    /**
     * Link words with the search phrase in them: "Ad günü hədiyyəsi", but
     * "Sevgiliyə hədiyyə" after a label that already says "to whom". The
     * owner can write the words himself instead.
     */
    public function linkText(): string
    {
        if (filled($this->link_text)) {
            return $this->link_text;
        }

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
     * The designs this page offers: its own picks, else the Azerbaijani
     * twin's (so a translation needs no list of its own), else every design.
     * Only ones a customer can open.
     *
     * @return Collection<int, Product>
     */
    public function shownProducts(): Collection
    {
        $picked = $this->pickedProducts();
        if ($picked->isEmpty() && $this->alt) {
            $picked = $this->alt->pickedProducts();
        }

        $products = $picked->isNotEmpty()
            ? $picked
            : Product::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return $products->filter->isCustomizable()->values();
    }

    /** @return Collection<int, Product> */
    private function pickedProducts(): Collection
    {
        return $this->products()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
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

    /** "13 dizayn" / "13 дизайнов", with the Russian ending the number asks for. */
    public function designCount(int $n): string
    {
        if ($this->locale !== 'ru') {
            return $n . ' dizayn';
        }

        $last = $n % 10;
        $word = $n % 100 >= 11 && $n % 100 <= 14 ? 'дизайнов'
            : ($last === 1 ? 'дизайн' : ($last >= 2 && $last <= 4 ? 'дизайна' : 'дизайнов'));

        return $n . ' ' . $word;
    }

    /** The page's own words, in its own language. */
    public function words(): array
    {
        return $this->locale === 'ru'
            ? [
                'home' => 'Главная', 'hub' => 'Идеи подарков', 'designs' => 'Подходящие дизайны',
                'pick' => 'Выбрать дизайн', 'how' => 'Как это работает?',
                'lede' => 'Выберите дизайн, загрузите фото и напишите текст — сразу увидите, как будет выглядеть коробка.',
                'fromPrice' => 'Коробка от :price', 'yours' => 'Ваше фото и ваши слова', 'delivery' => 'Доставка по Баку и регионам',
                'faq' => 'Вопросы', 'faqTitle' => 'Частые вопросы', 'others' => 'Другие идеи подарков',
                'all' => 'Все дизайны', 'other_lang' => 'Azərbaycanca',
            ]
            : [
                'home' => 'Ana səhifə', 'hub' => 'Hədiyyə fikirləri', 'designs' => 'Bu münasibətə uyğun dizaynlar',
                'pick' => 'Dizayn seç', 'how' => 'Necə işləyir?',
                'lede' => 'Birini seçin, şəklinizi yükləyin və sözlərinizi yazın — qutunun necə görünəcəyini dərhal görəcəksiniz.',
                'fromPrice' => 'Qutu :price-dan', 'yours' => 'Öz şəkliniz və sözləriniz', 'delivery' => 'Bakıda və bölgələrə çatdırılma',
                'faq' => 'Suallar', 'faqTitle' => 'Tez-tez soruşulan suallar', 'others' => 'Başqa hədiyyə fikirləri',
                'all' => 'Bütün dizaynlara bax', 'other_lang' => 'На русском',
            ];
    }
}
