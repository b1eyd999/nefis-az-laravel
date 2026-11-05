<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The card photographs, again - this time without asking the disk.
 *
 * The migration before this one set each xonça design's catalogue
 * photograph only `if (is_file(public_path($path)))`, which is true here and
 * false on the hosting: the deploy copies `public/` into the web root and
 * then deletes it from the application directory, so by the time migrations
 * run there is no `public/` to look in. Every design was skipped, and the
 * shelf went live showing the blank faces it prints from.
 *
 * The files travel in the same commit as this does, so their being there is
 * not in question. Nothing is asked of the disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('products')->where('category', 'xonca')->get(['id', 'slug']) as $design) {
            DB::table('products')->where('id', $design->id)
                ->whereNull('preview_image')
                ->update([
                    'preview_image' => 'images/xonca/numune/'
                        . str_replace('xonca-', '', $design->slug) . '.jpg',
                ]);
        }
    }

    public function down(): void
    {
        DB::table('products')->where('category', 'xonca')
            ->where('preview_image', 'like', 'images/xonca/numune/%')
            ->update(['preview_image' => null]);
    }
};
