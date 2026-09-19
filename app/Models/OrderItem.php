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
        'quantity',
        'price',
        'chocolate_id',
        'chocolate_name',
        'chocolate_price',
    ];

    protected function casts(): array
    {
        return [
            'customer_photos' => 'array',
            'custom_texts' => 'array',
            'photo_labels' => 'array',
            'text_labels' => 'array',
            'chocolate_price' => 'float',
        ];
    }

    /** One box with its bar, as ordered. */
    public function unitPrice(): float
    {
        return (float) ($this->price ?? 0) + (float) ($this->chocolate_price ?? 0);
    }

    /** The photo fields' names, as the customer page shows them ("1. Şəkil"). */
    public static function photoLabelsFor(Product $product): array
    {
        return $product->photoSlots->values()
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

            return ['label' => $slot->label ?: 'Mətn', 'fixed' => (bool) $slot->fixed, 'repeat' => $repeat];
        })->all();
    }

    /**
     * What the customer sent, paired with the field names they filled it in:
     * ['photos' => [[label, path]], 'texts' => [[label, value, fixed]]].
     * Orders from before names were kept borrow them from the design, if it
     * is still there.
     */
    public function fields(): array
    {
        $product = $this->product;
        $photoLabels = $this->photo_labels ?? ($product ? self::photoLabelsFor($product) : []);
        $textLabels = $this->text_labels ?? ($product ? self::textLabelsFor($product) : []);

        $photos = [];
        foreach (array_values((array) $this->customer_photos) as $i => $path) {
            $photos[] = ['label' => $photoLabels[$i] ?? ($i + 1) . '. Şəkil', 'path' => $path];
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
