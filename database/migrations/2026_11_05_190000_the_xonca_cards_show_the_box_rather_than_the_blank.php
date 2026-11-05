<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The catalogue card shows a box, not the blank it is printed from.
 *
 * A xonça design's artwork is the printed face with the writing left out —
 * the border, the ribbon, the wreath — because the names and the date are
 * slots the customer fills. That is right for printing and wrong for the
 * shelf: half the cards came out as empty cream rectangles, and a customer
 * scrolling past them has no idea what he is being offered.
 *
 * So each card gets a photograph of the box as it ends up: a mockup with
 * ordinary Azerbaijani names on it, the same picture the designs were drawn
 * against. `catalogImage()` prefers it over the bare template.
 *
 * The owner replaces any of them with a photograph of the real box the day
 * he has one — it is the «Önizləmə şəkli» field, nothing special.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('products')->where('category', 'xonca')->get(['id', 'slug']) as $design) {
            $id = str_replace('xonca-', '', $design->slug);
            $path = 'images/xonca/numune/' . $id . '.jpg';

            if (! is_file(public_path($path))) {
                continue;
            }

            DB::table('products')->where('id', $design->id)
                ->whereNull('preview_image')
                ->update(['preview_image' => $path]);
        }
    }

    public function down(): void
    {
        DB::table('products')->where('category', 'xonca')
            ->where('preview_image', 'like', 'images/xonca/numune/%')
            ->update(['preview_image' => null]);
    }
};
