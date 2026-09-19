<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner wants every box in every scene to take its design's colour.
 * Two scenes still held a colour picked while trying the swatches (with a
 * strong sheen and "dye everything", which also dyed the chocolate); they
 * now follow the design too. Their picked colour stays as the fallback for
 * a design whose colour is unknown, and the unchecked box can be ticked off
 * again in the scene editor.
 *
 * Only rewrites JSON, and only what still needs it, so a rerun is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('scenes')->get(['id', 'elements']) as $scene) {
            $elements = json_decode((string) $scene->elements, true) ?: [];
            $changed = false;
            foreach ($elements as &$el) {
                if (($el['type'] ?? null) === 'image' && empty($el['recolor'])) {
                    $el['recolor'] = true;
                    $el['sheen'] = 0;
                    $el['tint_all'] = false;
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
        // The previous colours were experiments; nothing to restore.
    }
};
