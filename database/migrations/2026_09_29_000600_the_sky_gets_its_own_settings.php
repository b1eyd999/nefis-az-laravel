<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a customer may choose about his own sky, and two more ways to print it.
 *
 * The ring stops being on-or-off — there is a quiet thin circle, the
 * graduated band, and the old double-ruled one — so `sky_ring` becomes a
 * word. What was `true` is the graduated band it always drew.
 *
 * The rest are the customer's switches, kept per window because the owner
 * decides which of them a design offers at all: constellation figures, their
 * names, the Milky Way, a small heart, and whether the hour is printed with
 * the date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_slots', function (Blueprint $t) {
            $t->string('sky_ring_kind', 8)->default('degrees')->after('sky_ring');
            $t->string('sky_choices', 60)->default('lines,labels,milky,heart')->after('sky_ring_kind');
            $t->boolean('sky_labels')->default(false)->after('sky_choices');
            $t->boolean('sky_milky')->default(false)->after('sky_labels');
            $t->boolean('sky_lines')->default(true)->after('sky_milky');
        });

        // The band it drew before is the graduated one; unticked meant no ring.
        \Illuminate\Support\Facades\DB::table('photo_slots')
            ->update(['sky_ring_kind' => \Illuminate\Support\Facades\DB::raw("CASE WHEN sky_ring = 1 THEN 'degrees' ELSE 'none' END")]);
    }

    public function down(): void
    {
        Schema::table('photo_slots', fn (Blueprint $t) => $t->dropColumn([
            'sky_ring_kind', 'sky_choices', 'sky_labels', 'sky_milky', 'sky_lines',
        ]));
    }
};
