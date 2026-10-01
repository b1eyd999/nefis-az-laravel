<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A window that holds the streets around a place instead of a photograph.
 *
 * Built the same way as the night sky next door: the owner sets the look in
 * the box editor, the customer only says where (and, if the design offers it,
 * how close in), and the order keeps the question rather than a picture, so
 * the sheet can be drawn again at printing size.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            // Which of the printed looks: ink, paper, sea or colour.
            'map_style' => fn (Blueprint $t) => $t->string('map_style', 16)->default('ink'),
            // How close in the map starts. 15 is a few streets across.
            'map_zoom' => fn (Blueprint $t) => $t->unsignedTinyInteger('map_zoom')->default(15),
            // The mark on the spot itself: none, a pin, a heart or a star.
            'map_marker' => fn (Blueprint $t) => $t->string('map_marker', 8)->default('heart'),
            // Comma-joined: which of those switches the customer may flip.
            'map_choices' => fn (Blueprint $t) => $t->string('map_choices', 60)->default('zoom'),
            // Whether the mark is on before he touches anything.
            'map_pin' => fn (Blueprint $t) => $t->boolean('map_pin')->default(true),
        ] as $column => $add) {
            if (! Schema::hasColumn('photo_slots', $column)) {
                Schema::table('photo_slots', $add);
            }
        }

        if (! Schema::hasColumn('order_items', 'street_map')) {
            Schema::table('order_items', function (Blueprint $t) {
                $t->json('street_map')->nullable()->after('star_map');
            });
        }
    }

    public function down(): void
    {
        foreach (['map_style', 'map_zoom', 'map_marker', 'map_choices', 'map_pin'] as $column) {
            if (Schema::hasColumn('photo_slots', $column)) {
                Schema::table('photo_slots', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }

        if (Schema::hasColumn('order_items', 'street_map')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('street_map'));
        }
    }
};
