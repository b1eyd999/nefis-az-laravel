<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A lock for everything on a design, not only for the uploaded pictures.
 *
 * An artwork layer could already be pinned down so a stray click would not
 * drag it; captions, windows and shapes could not, and a design with a dozen
 * of them is easy to nudge out of place while working on the one thing that
 * is still being drawn. The lock lives with the design, so it is still there
 * tomorrow.
 */
return new class extends Migration
{
    private const TABLES = ['photo_slots', 'text_slots', 'design_shapes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'locked')) {
                Schema::table($table, fn (Blueprint $t) => $t->boolean('locked')->default(false));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('locked'));
        }
    }
};
