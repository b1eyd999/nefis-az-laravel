<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One order for the whole design, instead of six bands around the photograph.
 *
 * A box was drawn in a fixed sequence — the layers under the photo, then the
 * shapes under it, then the customer's windows, then the shapes over, the
 * layers over, and the captions last — and `placement` was the only control
 * the owner had. He could not put a drawing between two faces, which is what
 * he asked for.
 *
 * `z` says where a row sits in that single stack. `sort_order` is left alone
 * and keeps the meaning it has always had: which field of the customer's form
 * a window or a caption belongs to. They have to be two columns — moving a
 * window up the stack must not quietly hand an already-ordered photograph to
 * a different window.
 *
 * The numbering below reproduces today's six bands exactly, so the first
 * drawing after this runs composites the same pixels; all that changed is
 * that the order is written down rather than implied.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    private const TABLES = ['design_layers', 'design_shapes', 'photo_slots', 'text_slots'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'z')) {
                Schema::table($table, function (Blueprint $t) {
                    // Nullable with no default on purpose: null means "this row
                    // is not in the free stack yet", and both the shop and the
                    // editor fall back to the old bands when they see one. That
                    // is what makes the gap between the files landing and this
                    // migration running harmless.
                    $t->unsignedInteger('z')->nullable();
                });
            }
        }

        /* The band order, written out here rather than called from
           App\Support\DesignStack. A migration has to do the same thing on a
           fresh install in a year's time as it did today, so it must not
           depend on code that is free to change. */
        Product::query()->select('id')->orderBy('id')->chunkById(50, function ($products) {
            foreach ($products as $product) {
                DB::transaction(function () use ($product) {
                    $own = fn (string $table) => DB::table($table)
                        ->where('product_id', $product->id)
                        ->orderBy('sort_order')->orderBy('id');
                    $slots = fn (string $table) => DB::table($table)
                        ->where('slotable_type', Product::class)
                        ->where('slotable_id', $product->id)
                        ->orderBy('sort_order')->orderBy('id');

                    $bands = [
                        ['design_layers', $own('design_layers')->where('placement', 'below')->pluck('id')],
                        ['design_shapes', $own('design_shapes')->where('placement', 'below')->pluck('id')],
                        ['photo_slots', $slots('photo_slots')->pluck('id')],
                        ['design_shapes', $own('design_shapes')->where('placement', 'above')->pluck('id')],
                        ['design_layers', $own('design_layers')->where('placement', 'above')->pluck('id')],
                        ['text_slots', $slots('text_slots')->pluck('id')],
                    ];

                    $z = 0;
                    foreach ($bands as [$table, $ids]) {
                        foreach ($ids as $id) {
                            // Row by row, and only where nothing is written yet:
                            // a run picked up months later must not overwrite an
                            // order the owner has since arranged by hand.
                            DB::table($table)->where('id', $id)->whereNull('z')->update(['z' => $z]);
                            $z++;
                        }
                    }
                });
            }
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'z')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('z');
                });
            }
        }
    }
};
