<?php

use App\Models\ProductCategory;
use App\Support\Xonca;
use Illuminate\Database\Migrations\Migration;

/**
 * A shelf for the small chocolates a xonça is piled with.
 *
 * The xonça page shows whatever is filed under this category, so the shelf
 * has to exist before the owner can file anything. It is made switched off:
 * an empty shelf on the catalogue page would read as a mistake, and the
 * owner turns it on himself once the first design is drawn.
 *
 * Rerun-safe: the slug is the key, and a shelf already there is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (ProductCategory::where('slug', Xonca::CATEGORY)->exists()) {
            return;
        }

        ProductCategory::create([
            'slug' => Xonca::CATEGORY,
            'name' => 'Xonça',
            'is_active' => false,
            'sort_order' => 90,
        ]);
    }

    public function down(): void
    {
        ProductCategory::where('slug', Xonca::CATEGORY)->whereDoesntHave('products')->delete();
    }
};
