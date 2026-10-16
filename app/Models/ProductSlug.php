<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An address a design used to be published at.
 *
 * Only ever written by the Product model when a slug changes, and only ever
 * read when a slug is asked for that no design answers to any more.
 */
class ProductSlug extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['product_id', 'slug'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
