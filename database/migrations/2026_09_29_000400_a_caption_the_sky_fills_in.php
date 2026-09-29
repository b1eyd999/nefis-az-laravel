<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A caption nobody types, because the star map already knows it.
 *
 * Under the sky on these designs go the coordinates of the place and the
 * date of the night — "38°47'33\"N 48°28'47\"E", "14 Fevral 2026". Asking the
 * customer to copy those out of the page and into a box would be asking him
 * to make a mistake; the shop has them already, exactly, from the same place
 * and hour he picked. So the caption fills itself and is never shown as a
 * field at all.
 *
 * 'none' keeps every caption already drawn exactly as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_slots', fn (Blueprint $t) => $t->string('auto', 12)->default('none')->after('link_key'));
    }

    public function down(): void
    {
        Schema::table('text_slots', fn (Blueprint $t) => $t->dropColumn('auto'));
    }
};
