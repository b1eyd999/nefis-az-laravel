<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A photo window that holds the sky instead of a photograph.
 *
 * "The stars over the place we met, that night" is the same kind of thing as
 * a photograph as far as the design is concerned: a shape somewhere on the
 * box that something is poured into. So it is not a new kind of slot with its
 * own dragging, resizing and saving — it is the slot the editor already has,
 * told what falls into it. Everything the owner already knows how to do keeps
 * working, and nothing about photographs changes.
 *
 * `star_map` on the order line keeps what the customer chose — the date, the
 * hour, the place and its coordinates — never a picture, so the map can be
 * drawn again at printing size years later, exactly as he saw it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_slots', function (Blueprint $t) {
            $t->string('fill', 16)->default('photo')->after('label');
            $t->string('sky_style', 16)->nullable()->after('fill');
            $t->boolean('sky_ring')->default(true)->after('sky_style');
        });

        Schema::table('order_items', fn (Blueprint $t) => $t->json('star_map')->nullable()->after('photo_frames'));
    }

    public function down(): void
    {
        Schema::table('photo_slots', fn (Blueprint $t) => $t->dropColumn(['fill', 'sky_style', 'sky_ring']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('star_map'));
    }
};
