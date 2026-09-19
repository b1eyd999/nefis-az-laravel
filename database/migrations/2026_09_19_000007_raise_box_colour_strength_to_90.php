<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The owner found box colours at 70 % too faint and asked for 20 % more.
 * Scenes saved while 70 was the default hold it explicitly; they move to
 * the new default of 90 like the rest. A strength the owner set by hand to
 * anything else is left alone.
 *
 * Only rewrites JSON, and only what still reads 70, so a rerun is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('scenes')->get(['id', 'elements']) as $scene) {
            $elements = json_decode((string) $scene->elements, true) ?: [];
            $changed = false;
            foreach ($elements as &$el) {
                if (($el['type'] ?? null) === 'image' && (int) ($el['tint_strength'] ?? 0) === 70) {
                    $el['tint_strength'] = 90;
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
        // 70 was the default only for a few minutes; nothing to restore.
    }
};
