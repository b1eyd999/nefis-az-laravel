<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A finished design put on a shelf, to start the next box from.
 *
 * `payload` is the design exactly as the box editor holds it; `product_id` is
 * only where it came from, and the template outlives that box being deleted.
 */
class DesignTemplate extends Model
{
    protected $fillable = ['name', 'product_id', 'preview', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function toEditor(): array
    {
        $design = (array) $this->payload;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->preview ? Media::url($this->preview) : null,
            'from' => $this->product?->name,
            'counts' => [
                'layers' => count($design['layers'] ?? []),
                'shapes' => count($design['shapes'] ?? []),
                'photos' => count($design['photos'] ?? []),
                'texts' => count($design['texts'] ?? []),
            ],
        ];
    }
}
