<?php

namespace App\Models;

use App\Support\ImageColor;
use App\Support\ImageStore;
use App\Support\Media;
use App\Support\YandexDisk;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use \App\Models\Concerns\Translatable;

    use HasFactory;

    protected $fillable = [
        'i18n',
        'name',
        'slug',
        'description',
        'tag',
        'category',
        // Designs built around a song: the customer pastes a Spotify link and
        // the scannable code goes on the box. Off everywhere else.
        'spotify_code',
        'preview_image',
        'poster_url',
        'box_color',
        'cover_scene_id',
        'price',
        'template_image',
        'overlay_image',
        'background_image',
        'background_width',
        'background_height',
        'box_area_x',
        'box_area_y',
        'box_area_width',
        'box_area_height',
        'box_area_rotation',
        'content_x',
        'content_y',
        'content_width',
        'content_height',
        'content_rotation',
        'template_width',
        'template_height',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'i18n' => 'array',
            'is_active' => 'boolean',
            'price' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // A box's uploads live in their own folder; nothing else uses them.
        static::deleted(fn (Product $product) => Storage::disk('public')->deleteDirectory($product->assetDirectory()));

        /* Renaming a design changes its address. The old one is written down
           here so it can be redirected instead of answering 404 — see
           resolveRouteBinding below, and the product_slugs migration for why
           that matters more than it sounds. */
        static::updating(function (Product $product) {
            if (! $product->isDirty('slug')) {
                return;
            }

            $was = (string) $product->getOriginal('slug');

            if ($was !== '') {
                ProductSlug::updateOrCreate(['slug' => $was], ['product_id' => $product->id]);
            }

            /* The design has just taken an address some design used to have
               — its own, renamed back, or one freed by another. A live
               address must never also be a forwarding address, or the
               redirect sends it to itself. */
            ProductSlug::where('slug', $product->slug)->delete();
        });

        // A slug chosen at birth that was once somebody else's: same rule.
        static::created(fn (Product $product) => ProductSlug::where('slug', $product->slug)->delete());
    }

    /**
     * Find the design this address belongs to — including the addresses it
     * used to have.
     *
     * A slug that no design answers to is looked up among the old ones, and
     * if it is found the visitor is sent on permanently. `abort()` with a
     * response throws it from here, which is the only way out of a route
     * binding: returning null would be a 404, and by the time the controller
     * runs the binding has already given up.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $product = parent::resolveRouteBinding($value, $field);

        if ($product) {
            return $product;
        }

        if (($field ?? $this->getRouteKeyName()) !== 'slug') {
            return null;
        }

        $moved = ProductSlug::with('product')->where('slug', $value)->first()?->product;

        // A design that is switched off has no address to send anyone to:
        // 404 is the honest answer, and the one Google wants.
        if (! $moved || ! $moved->is_active) {
            return null;
        }

        abort(redirect($this->movedTo((string) $value, $moved->slug), 301));
    }

    /**
     * The address asked for, with the old slug swapped for the new one.
     *
     * Built from the request rather than from a route name so it works
     * wherever a design appears in a path — /products/x/customize, the same
     * under /ru and /en, the box editor — and keeps whatever came after the
     * question mark.
     */
    private function movedTo(string $was, string $now): string
    {
        $segments = array_map(
            fn (string $segment) => $segment === $was ? $now : $segment,
            request()->segments()
        );

        return url(implode('/', $segments))
            . (($query = request()->getQueryString()) ? '?' . $query : '');
    }

    /** Addresses this design used to be published at. */
    public function oldSlugs(): HasMany
    {
        return $this->hasMany(ProductSlug::class);
    }

    /**
     * Start forwarding these addresses here.
     *
     * An address can only lead to one place, so one already pointing at
     * another design is taken over rather than duplicated — the owner typing
     * it here is saying where it should go now. The design's own current
     * address is refused outright: it would redirect to itself.
     *
     * @param  iterable<int, string>  $slugs
     */
    public function rememberOldSlugs(iterable $slugs): void
    {
        foreach ($slugs as $slug) {
            $slug = trim((string) $slug);

            if ($slug === '' || $slug === $this->slug) {
                continue;
            }

            // Nor an address some design actually answers to today.
            if (static::where('slug', $slug)->exists()) {
                continue;
            }

            ProductSlug::updateOrCreate(['slug' => $slug], ['product_id' => $this->id]);
        }
    }

    /** @param  iterable<int, string>  $slugs */
    public function forgetOldSlugs(iterable $slugs): void
    {
        $slugs = array_filter(array_map(fn ($s) => trim((string) $s), iterator_to_array($slugs, false)));

        if ($slugs !== []) {
            $this->oldSlugs()->whereIn('slug', $slugs)->delete();
        }
    }

    /** Where the box editor keeps this product's uploaded artwork. */
    public function assetDirectory(): string
    {
        return 'boxes/' . $this->id;
    }

    /**
     * The least a customer can pay for this box: its price plus the cheapest
     * bar on sale, because a bar always goes in. The bar is looked up once
     * per request — a catalogue page prices twenty-seven boxes.
     */
    public function priceFrom(): ?float
    {
        if (! $this->price) {
            return null;
        }

        static $bar = null;
        $bar ??= (float) (Chocolate::shown()->get()->map->toCustomer()->pluck('price')->filter(fn ($p) => $p > 0)->min() ?? 0);

        return round((float) $this->price + $bar, 2);
    }

    /**
     * The picture on the catalogue card: the owner's poster photo, else the
     * cover drawn in his chosen scene, else the plain visual.
     */
    public function catalogImage(): ?string
    {
        return $this->poster_image ?: $this->cover_image ?: $this->preview_image ?: $this->template_image;
    }

    /**
     * The catalogue picture at the size a phone actually paints it, when one
     * has been made. Remote artwork has none, and the card then serves the
     * full picture as it always did.
     */
    public function catalogImageSmall(int $side = 400, bool $make = false): ?string
    {
        $path = $this->catalogImage();

        if (blank($path) || Media::isRemote($path)) {
            return null;
        }

        return ImageStore::smaller($path, $side, $make);
    }

    /**
     * Brings the poster in from its Yandex Disk link and keeps it as a small
     * WebP beside the box's other artwork.
     *
     * @throws \RuntimeException with a message for the owner
     */
    public function importPoster(?string $link = null): void
    {
        $link = trim($link ?? (string) $this->poster_url);
        $this->storePoster(YandexDisk::downloadImage($link), $link);
    }

    /** Keeps a poster already downloaded from `$link`, and deletes the download. */
    public function storePoster(UploadedFile $file, string $link): void
    {
        try {
            [$path] = ImageStore::store($file, $this->assetDirectory(), 'poster', 85, 1600);
        } finally {
            @unlink($file->getRealPath());
        }

        $this->dropPosterFile();
        $this->forceFill(['poster_url' => $link, 'poster_image' => $path])->saveQuietly();
    }

    public function removePoster(): void
    {
        $this->dropPosterFile();
        $this->forceFill(['poster_url' => null, 'poster_image' => null])->saveQuietly();
    }

    private function dropPosterFile(): void
    {
        if ($this->poster_image && str_starts_with($this->poster_image, $this->assetDirectory() . '/')) {
            Storage::disk('public')->delete($this->poster_image);
        }
    }

    /**
     * The colour the box is dyed in the scenes: the owner's own pick, else the
     * colour the design runs out to at its edges, read once from the visual.
     */
    public function effectiveBoxColor(): ?string
    {
        if ($this->box_color) {
            return $this->box_color;
        }
        if (! $this->box_color_auto && $this->preview_image && $this->exists) {
            /* Once a day per visual, not once per visitor. A design whose
               edges are transparent — ordinary for box artwork — has no
               colour to find, so the column stays empty and the next
               request read the whole file off the disk and decoded it
               again. The key carries the image's own name, so a visual the
               owner replaces is looked at straight away. */
            $key = 'box-colour:' . md5((string) $this->preview_image);
            if (! Cache::has($key)) {
                Cache::put($key, true, now()->addDay());
                $this->refreshAutoBoxColor();
            }
        }

        return $this->box_color_auto;
    }

    public function refreshAutoBoxColor(): void
    {
        $disk = Storage::disk('public');
        $auto = $this->preview_image && $disk->exists($this->preview_image)
            ? ImageColor::edgeColor($disk->path($this->preview_image))
            : null;
        $this->forceFill(['box_color_auto' => $auto])->saveQuietly();
    }

    /**
     * What the admin's browser needs to draw this product's cover: the visual
     * corner-pinned into the chosen scene. Null when there is nothing to draw.
     */
    public function coverJob(): ?array
    {
        $scene = $this->coverScene;
        if (! $scene || ! $this->preview_image) {
            return null;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'visual' => Media::url($this->preview_image),
            'boxColor' => $this->effectiveBoxColor(),
            'scene' => $scene->toCustomer(),
            'upload' => route('cover.store', $this->id),
        ];
    }

    public function coverScene(): BelongsTo
    {
        return $this->belongsTo(Scene::class, 'cover_scene_id');
    }

    /**
     * A product can be customised once it has artwork: layers built in the box
     * editor, a single template image from before the editor existed, or —
     * since shapes and star maps — anything at all placed on the canvas. A
     * design whose whole face is a filled rectangle and the night sky carries
     * no image file, and it is no less a design for that.
     */
    public function isCustomizable(): bool
    {
        if (filled($this->template_image)) {
            return true;
        }

        /* A list that already counted them does not ask again: the admin's
           table draws a hundred rows. */
        foreach (['layers_count', 'shapes_count', 'photo_slots_count'] as $counted) {
            if (isset($this->attributes[$counted]) && $this->attributes[$counted] > 0) {
                return true;
            }
        }

        return $this->drawnOn ??= $this->layers()->exists()
            || $this->shapes()->exists()
            || $this->photoSlots()->exists();
    }

    /** Remembered for as long as this one design is in hand. */
    protected ?bool $drawnOn = null;

    /**
     * Nothing has been drawn on it yet.
     *
     * A design like this cannot be customised and must not be for sale: the
     * customer would open an empty box. The shop switches it off by itself
     * when the editor leaves it empty, and says so in the admin.
     */
    public function isBlank(): bool
    {
        return ! $this->isCustomizable();
    }

    public function isBox(): bool
    {
        return $this->relationLoaded('layers') ? $this->layers->isNotEmpty() : $this->layers()->exists();
    }

    /**
     * The shelves, key => the name a reader sees. The owner arranges them in
     * the admin; asked for many times while a catalogue page is drawn, so the
     * answer is kept for the rest of the request.
     *
     * @return array<string, string>
     */
    public static function categories(bool $onlyShown = true): array
    {
        static $cache = [];
        $key = $onlyShown ? 'shown' : 'all';

        return $cache[$key] ??= ProductCategory::query()
            ->when($onlyShown, fn ($q) => $q->where('is_active', true))
            ->inOrder()
            ->get()
            ->mapWithKeys(fn (ProductCategory $c) => [$c->slug => $c->label()])
            ->all();
    }

    public function categoryLabel(): ?string
    {
        return self::categories(false)[$this->category] ?? $this->category;
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(DesignLayer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function shapes(): HasMany
    {
        return $this->hasMany(DesignShape::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Scenes picked for this product; none picked means every active one. */
    public function scenes(): BelongsToMany
    {
        return $this->belongsToMany(Scene::class)->orderBy('scenes.sort_order')->orderBy('scenes.id');
    }

    /** The gift-idea pages (/hediyye/...) this design is offered on. */
    public function giftPages(): BelongsToMany
    {
        return $this->belongsToMany(GiftPage::class);
    }

    public function angles(): HasMany
    {
        return $this->hasMany(ProductAngle::class)->orderBy('sort_order');
    }

    public function photoSlots(): MorphMany
    {
        return $this->morphMany(PhotoSlot::class, 'slotable')->orderBy('sort_order')->orderBy('id');
    }

    public function textSlots(): MorphMany
    {
        return $this->morphMany(TextSlot::class, 'slotable')->orderBy('sort_order')->orderBy('id');
    }
}
