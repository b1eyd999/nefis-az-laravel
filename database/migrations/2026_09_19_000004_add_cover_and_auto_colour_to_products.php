<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A product's catalogue cover is its design shown in a scene the owner
 * picks, and its box takes the design's own colour unless the owner sets one.
 *
 * cover_scene_id  the scene the cover is drawn in (no DB-level foreign key:
 *                 Scene's delete hook clears it, which also drops the image)
 * cover_image     the drawn cover, made in the admin's browser
 * box_color_auto  the colour read off the edges of the design's visual
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'cover_scene_id' => fn (Blueprint $t) => $t->unsignedBigInteger('cover_scene_id')->nullable()->index(),
            'cover_image' => fn (Blueprint $t) => $t->string('cover_image')->nullable(),
            'box_color_auto' => fn (Blueprint $t) => $t->string('box_color_auto', 9)->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('products', $column)) {
                Schema::table('products', $add);
            }
        }

        // Box colours now follow each design by default, so renders already
        // placed without a fixed colour of their own start following it too.
        foreach (DB::table('scenes')->get(['id', 'elements']) as $scene) {
            $elements = json_decode((string) $scene->elements, true) ?: [];
            $changed = false;
            foreach ($elements as &$el) {
                if (($el['type'] ?? null) === 'image' && empty($el['tint']) && empty($el['recolor'])) {
                    $el['recolor'] = true;
                    $changed = true;
                }
            }
            unset($el);
            if ($changed) {
                DB::table('scenes')->where('id', $scene->id)->update(['elements' => json_encode($elements)]);
            }
        }
    }

    public function down(): void
    {
        foreach (['cover_scene_id', 'cover_image', 'box_color_auto'] as $column) {
            if (Schema::hasColumn('products', $column)) {
                Schema::table('products', function (Blueprint $t) use ($column) {
                    if ($column === 'cover_scene_id') {
                        $t->dropIndex(['cover_scene_id']);
                    }
                    $t->dropColumn($column);
                });
            }
        }
    }
};
