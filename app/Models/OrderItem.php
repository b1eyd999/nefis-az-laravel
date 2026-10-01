<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'customer_photos',
        'custom_texts',
        'photo_labels',
        'text_labels',
        // How each photo was zoomed, turned and shifted in its window.
        'photo_frames',
        // The night sky the customer chose: date, hour, place, coordinates.
        'star_map',
        'street_map',
        // The song on the box, as spotify:track:… — the canonical form, so the
        // code can be redrawn at any size years after the order.
        'spotify_uri',
        'quantity',
        'price',
        'chocolate_id',
        'chocolate_name',
        'chocolate_price',
        'chocolate_cost',
        // The gift wrap, as it was when ordered.
        'wrapping_id',
        'wrapping_name',
        'wrapping_price',
        // A Polaroid letter: inside the box, or the whole line when ordered alone.
        'letter_text',
        'letter_photo',
        'letter_price',
        // A live photo (AR): the customer's video, until the owner moves it to Yandex Disk.
        'ar_video',
        'ar_price',
    ];

    protected function casts(): array
    {
        return [
            'customer_photos' => 'array',
            'custom_texts' => 'array',
            'photo_labels' => 'array',
            'text_labels' => 'array',
            'photo_frames' => 'array',
            'star_map' => 'array',
            'street_map' => 'array',
            'chocolate_price' => 'float',
            'wrapping_price' => 'float',
            'letter_price' => 'float',
            'ar_price' => 'float',
            'price' => 'float',
        ];
    }

    /** One box with its bar, wrap and letter, as ordered. */
    public function unitPrice(): float
    {
        return (float) ($this->price ?? 0) + (float) ($this->chocolate_price ?? 0) + (float) ($this->wrapping_price ?? 0)
            + (float) ($this->letter_price ?? 0) + (float) ($this->ar_price ?? 0);
    }

    public function arVideoUrl(): ?string
    {
        return $this->ar_video ? \App\Support\Media::url($this->ar_video) : null;
    }

    public function livePhotos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LivePhoto::class);
    }

    public function hasLetter(): bool
    {
        return $this->letter_price !== null || filled($this->letter_text) || filled($this->letter_photo);
    }

    /** A Polaroid letter ordered on its own: no box on this line. */
    public function isLetterOnly(): bool
    {
        return $this->product_id === null && $this->hasLetter();
    }

    public function letterPhotoUrl(): ?string
    {
        return $this->letter_photo ? \App\Support\Media::url($this->letter_photo) : null;
    }

    /** The photo fields' names, as the customer page shows them ("1. Şəkil"). */
    public static function photoLabelsFor(Product $product): array
    {
        // A window filled with the night sky asks for nothing and is not
        // listed: the photographs the customer sent, and their names, stay
        // one-to-one.
        return $product->photoSlots->values()
            ->reject(fn ($slot) => ! $slot->needsUpload())
            ->values()
            ->map(fn ($slot, $i) => ($i + 1) . '. ' . ($slot->label ?: 'Şəkil'))
            ->all();
    }

    /**
     * The caption fields' names, as the customer page shows them. A caption
     * fixed in the design was never asked for, and a repeat of a linked name
     * was filled by the field before it; both are marked so.
     */
    public static function textLabelsFor(Product $product): array
    {
        $seen = [];

        return $product->textSlots->values()->map(function ($slot) use (&$seen) {
            $repeat = $slot->link_key && in_array($slot->link_key, $seen, true);
            if ($slot->link_key) {
                $seen[] = $slot->link_key;
            }

            // A caption the sky filled in was not asked for either, so it is
            // shown the same way: the value matters, the empty field does not.
            return ['label' => $slot->label ?: 'Mətn', 'fixed' => $slot->isGiven(), 'repeat' => $repeat];
        })->all();
    }

    /**
     * What the customer sent, paired with the field names they filled it in:
     * ['photos' => [[label, path, frame]], 'texts' => [[label, value, fixed]]].
     * `frame` is how the photo sat in its window when the order was placed,
     * or null for orders from before that was kept, or an untouched photo.
     * Orders from before names were kept borrow them from the design, if it
     * is still there.
     */
    public function fields(): array
    {
        $product = $this->product;
        $photoLabels = $this->photo_labels ?? ($product ? self::photoLabelsFor($product) : []);
        $textLabels = $this->text_labels ?? ($product ? self::textLabelsFor($product) : []);

        $photos = [];
        $frames = array_values((array) $this->photo_frames);
        foreach (array_values((array) $this->customer_photos) as $i => $path) {
            $photos[] = ['label' => $photoLabels[$i] ?? ($i + 1) . '. Şəkil', 'path' => $path,
                'frame' => self::frameOrNull($frames[$i] ?? null)];
        }

        $texts = [];
        foreach (array_values((array) $this->custom_texts) as $i => $value) {
            $meta = $textLabels[$i] ?? ['label' => 'Mətn ' . ($i + 1), 'fixed' => false, 'repeat' => false];
            if (! empty($meta['repeat'])) {
                continue;   // the same name again, already listed
            }
            $texts[] = ['label' => $meta['label'], 'value' => (string) $value, 'fixed' => ! empty($meta['fixed'])];
        }

        return ['photos' => $photos, 'texts' => $texts];
    }

    /**
     * A frame worth showing: one the customer (or the face finder) actually
     * changed. The default — fit, upright, centred — says nothing.
     */
    public static function frameOrNull(mixed $frame): ?array
    {
        if (! is_array($frame)) {
            return null;
        }
        $f = [
            'scale' => round((float) ($frame['scale'] ?? 1), 3),
            'rotate' => round((float) ($frame['rotate'] ?? 0), 1),
            'flip' => (bool) ($frame['flip'] ?? false),
            'panX' => round((float) ($frame['panX'] ?? 0), 3),
            'panY' => round((float) ($frame['panY'] ?? 0), 3),
            'ratio' => round((float) ($frame['ratio'] ?? 1), 3),
            'shape' => ($frame['shape'] ?? 'rectangle') === 'ellipse' ? 'ellipse' : 'rectangle',
        ];
        $untouched = abs($f['scale'] - 1) < 0.005 && abs($f['rotate']) < 0.05 && ! $f['flip']
            && abs($f['panX']) < 0.005 && abs($f['panY']) < 0.005;

        return $untouched ? null : $f;
    }

    /** The page a phone opens when the printed code is scanned. */
    public function spotifyLink(): ?string
    {
        return $this->spotify_uri ? \App\Support\SpotifyCode::link($this->spotify_uri) : null;
    }

    /** The code as a picture: svg to print from, png to look at. */
    public function spotifyImage(string $format = 'png', int $width = 640): ?string
    {
        return $this->spotify_uri
            ? \App\Support\SpotifyCode::image($this->spotify_uri, $format, 'ffffff', 'black', $width)
            : null;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
