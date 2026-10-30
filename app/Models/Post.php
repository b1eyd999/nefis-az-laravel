<?php

namespace App\Models;

use App\Support\Locale;
use App\Support\Media;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * One article in the shop's blog: /bloq/{slug} in Azerbaijani, /ru/blog/…
 * and /en/blog/… in the other two languages. The owner writes them in
 * admin → Bloq.
 *
 * An article belongs to one language, as a gift page does, and the Russian
 * and English versions hang off the Azerbaijani one through `alt_of`: that
 * is what pairs them for search engines and what lets a reader switch
 * language without landing on the home page.
 */
class Post extends Model
{
    public const LOCALES = ['az' => 'Azərbaycanca', 'ru' => 'Rusca', 'en' => 'İngiliscə'];

    /** How many words a minute the reading time is counted at. */
    private const PACE = 170;

    protected $fillable = [
        'slug', 'locale', 'alt_of', 'topic', 'emoji', 'title',
        'meta_title', 'meta_description', 'excerpt', 'body', 'cover', 'faq',
        'published_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'faq' => 'array',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    /** The Azerbaijani article this one is a translation of. */
    public function alt(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'alt_of');
    }

    /** The translations pointing at this article. */
    public function alternates(): HasMany
    {
        return $this->hasMany(Post::class, 'alt_of');
    }

    /**
     * The articles a reader may see: switched on, dated, and the date gone
     * by. A date in the future is how the owner writes an article today and
     * has it appear on Monday.
     */
    public function scopeShown(Builder $query): Builder
    {
        /* The date comparison below already drops a row with no date — in
           SQL, NULL <= now() is neither true nor false — so this line
           changes no result and no test can kill it. It stays because
           «it has a date, and the date has passed» is what the scope means,
           and the next person to touch the comparison should not have to
           know three-valued logic to keep that true. */
        return $query->where('is_active', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function scopeInLocale(Builder $query, string $locale): Builder
    {
        return $query->where('locale', $locale);
    }

    /** Whether this very row would be shown, without asking the database again. */
    public function isShown(): bool
    {
        return $this->is_active
            && $this->published_at !== null
            && ! $this->published_at->isFuture();
    }

    public function url(): string
    {
        return route(Locale::route('blog.show', $this->locale), $this->slug);
    }

    public static function hubUrl(string $locale): string
    {
        return route(Locale::route('blog.index', $locale));
    }

    /**
     * This article in another language. The Azerbaijani row is the original
     * and the translations hang off it, so the walk always goes through it.
     * With no translation written the reader lands on that language's blog
     * rather than on the home page — at least he is still among the articles.
     */
    public function localisedUrl(string $locale): string
    {
        if ($this->locale === $locale) {
            return $this->url();
        }

        $root = $this->alt ?: $this;

        if ($root->locale === $locale) {
            return $root->isShown() ? $root->url() : self::hubUrl($locale);
        }

        $twin = $root->alternates()->shown()->inLocale($locale)->first();

        return $twin?->url() ?? self::hubUrl($locale);
    }

    /**
     * The whole family of this article, one row per language and only
     * languages the shop publishes — what hreflang is built from.
     *
     * @return \Illuminate\Support\Collection<int, Post>
     */
    public function family(): \Illuminate\Support\Collection
    {
        $root = $this->alt ?: $this;

        /* collect() rather than pushing onto $root->alternates: that is the
           loaded relation itself, and pushing the parent into it leaves the
           article listed among its own translations for the rest of the
           request. */
        return collect([$this, $root])->merge($root->alternates)
            ->filter->isShown()
            ->filter(fn (Post $twin) => in_array($twin->locale, Locale::published(), true))
            ->unique('locale')
            ->values();
    }

    public function metaTitle(): string
    {
        return $this->meta_title ?: $this->title . ' | Nefis';
    }

    public function metaDescription(): string
    {
        return $this->meta_description ?: Seo::snippet($this->excerpt ?: $this->body);
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
        return array_values(array_filter(
            (array) $this->faq,
            fn ($f) => is_array($f) && filled($f['q'] ?? null) && filled($f['a'] ?? null),
        ));
    }

    /** The card's picture, or null — a card without one shows its emoji. */
    public function coverUrl(): ?string
    {
        return Media::url($this->cover);
    }

    /**
     * The first two or three lines on a card. Taken from the article itself
     * when the owner has not written a lede of its own, so a card is never
     * a bare heading.
     */
    public function lede(int $limit = 150): string
    {
        $text = filled($this->excerpt)
            ? (string) $this->excerpt
            : trim(preg_replace('/\s+/u', ' ', strip_tags($this->bodyHtml()->toHtml())));

        return Str::limit($text, $limit);
    }

    /** "5 dəq oxu" / "5 мин чтения" / "5 min read". */
    public function readingMinutes(): int
    {
        $words = preg_split('/[^\p{L}\p{N}\'’-]+/u', (string) $this->body, -1, PREG_SPLIT_NO_EMPTY);

        return max(1, (int) ceil(count($words ?: []) / self::PACE));
    }

    /**
     * The date in the article's own language: "30 oktyabr 2026".
     *
     * Written out here rather than left to Carbon, which translates a month
     * only as far as the locale files shipped with it reach — Azerbaijani
     * among them was printing English month names on the hosting.
     */
    public function dateText(): string
    {
        $date = $this->published_at;

        if (! $date instanceof Carbon) {
            return '';
        }

        $months = [
            'az' => ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul',
                'avqust', 'sentyabr', 'oktyabr', 'noyabr', 'dekabr'],
            'ru' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля',
                'августа', 'сентября', 'октября', 'ноября', 'декабря'],
            'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July',
                'August', 'September', 'October', 'November', 'December'],
        ];

        $month = ($months[$this->locale] ?? $months['az'])[$date->month - 1];

        return $this->locale === 'en'
            ? $month . ' ' . $date->day . ', ' . $date->year
            : $date->day . ' ' . $month . ' ' . $date->year;
    }

    /**
     * The designs this article offers, if the owner picked any: its own,
     * else its Azerbaijani twin's, so a translation needs no list of its
     * own. Unlike a gift page, an empty list means none — an article about
     * choosing a photograph has no business listing the whole catalogue.
     *
     * @return Collection<int, Product>
     */
    public function shownProducts(): Collection
    {
        $picked = $this->pickedProducts();

        if ($picked->isEmpty() && $this->alt) {
            $picked = $this->alt->pickedProducts();
        }

        return $picked->filter->isCustomizable()->values();
    }

    /** @return Collection<int, Product> */
    private function pickedProducts(): Collection
    {
        return $this->products()->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    /** The article's own words, in the language the article is written in. */
    public function words(): array
    {
        $was = app()->getLocale();
        app()->setLocale($this->locale);

        try {
            return [
                'home' => __('Ana səhifə'),
                'hub' => __('Bloq'),
                'read' => __(':n dəq oxu'),
                'designs' => __('Bu yazıda danışdığımız dizaynlar'),
                'pick' => __('Dizayn seç'),
                'faqTitle' => __('Tez-tez soruşulan suallar'),
                'others' => __('Digər yazılar'),
                'all' => __('Bütün dizaynlara bax'),
                'cta' => __('Öz qutunuzu yığın'),
                'ctaText' => __('Dizaynı seçin, şəklinizi yükləyin və sözlərinizi yazın — qutunun necə görünəcəyini sifariş etməzdən əvvəl ekranda görürsünüz.'),
            ];
        } finally {
            app()->setLocale($was);
        }
    }

    /** "5 dəq oxu", with the number in it. */
    public function readingText(): string
    {
        return str_replace(':n', (string) $this->readingMinutes(), $this->words()['read']);
    }
}
