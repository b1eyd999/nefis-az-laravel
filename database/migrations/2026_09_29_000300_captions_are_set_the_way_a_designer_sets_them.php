<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The typography a caption was designed with.
 *
 * Until now a caption on a box carried a font, a size and a colour, and that
 * was all — so a design drawn in Photoshop with letters set wide apart, or in
 * capitals, could not be reproduced here at all. These are the controls from
 * that panel, in the same units it uses:
 *
 *  - `tracking`, thousandths of the font size, exactly Photoshop's VA field;
 *  - `line_height`, per cent of the font size (120 is the old fixed value);
 *  - `text_case`, printed as written, ALL CAPS, or small capitals;
 *  - `scale_x` / `scale_y`, per cent, the panel's two 100% boxes;
 *  - `baseline_shift`, pixels up from the line, as its "0 pt" field.
 *
 * The defaults are what every existing caption was already being drawn with,
 * so nothing that is already on sale changes by a hair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('text_slots', function (Blueprint $t) {
            $t->smallInteger('tracking')->default(0)->after('font_weight');
            $t->unsignedSmallInteger('line_height')->default(120)->after('tracking');
            $t->string('text_case', 8)->default('none')->after('line_height');
            $t->unsignedSmallInteger('scale_x')->default(100)->after('text_case');
            $t->unsignedSmallInteger('scale_y')->default(100)->after('scale_x');
            $t->smallInteger('baseline_shift')->default(0)->after('scale_y');
        });
    }

    public function down(): void
    {
        Schema::table('text_slots', fn (Blueprint $t) => $t->dropColumn([
            'tracking', 'line_height', 'text_case', 'scale_x', 'scale_y', 'baseline_shift',
        ]));
    }
};
