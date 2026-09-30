<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The little heart over the middle of the sky belongs to the design, not to
 * the order form.
 *
 * It went out as one more switch under the date, and it does not belong
 * there: it is a decision about how the box looks, which the owner makes once
 * when he draws the design, the way he decides about the ring or the colour.
 * So it gets its own setting beside the other three, and is taken out of the
 * list of things the customer is asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photo_slots', fn (Blueprint $t) => $t->boolean('sky_heart')->default(false)->after('sky_lines'));

        // Nobody is asked about it any more; the designs already made keep
        // every other switch they offered.
        foreach (DB::table('photo_slots')->whereNotNull('sky_choices')->get(['id', 'sky_choices']) as $slot) {
            $left = array_values(array_diff(array_filter(explode(',', (string) $slot->sky_choices)), ['heart']));
            DB::table('photo_slots')->where('id', $slot->id)->update(['sky_choices' => implode(',', $left)]);
        }
    }

    public function down(): void
    {
        Schema::table('photo_slots', fn (Blueprint $t) => $t->dropColumn('sky_heart'));
    }
};
