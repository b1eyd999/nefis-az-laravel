<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Designs that are on the site with nothing drawn on them come off it.
 *
 * A product is made in the admin before it is drawn in the editor, and if the
 * "show on the site" switch was left on in between, the catalogue showed a
 * card that opened an empty box. From now on the editor switches such a
 * design off by itself when it is saved empty; this is the same sweep for the
 * ones already standing there.
 *
 * Nothing that has artwork is touched, and nothing is switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::table('products')
            ->where('is_active', true)
            ->whereNull('template_image')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('design_layers')->whereColumn('design_layers.product_id', 'products.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('design_shapes')->whereColumn('design_shapes.product_id', 'products.id'))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('photo_slots')
                ->whereColumn('photo_slots.slotable_id', 'products.id')
                ->where('photo_slots.slotable_type', 'App\\Models\\Product'))
            ->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Switching them back on would put empty designs in front of
        // customers again; the owner turns on the ones he wants.
    }
};
