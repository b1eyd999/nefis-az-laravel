<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The preview the customer approved is almost never the raw upload: face
 * detection moves the photo before they touch anything, and they zoom, turn
 * and drag it after. Only the file was sent, and the shop placed every photo
 * again by hand — a mismatch showed up when the box already existed.
 *
 * One entry per photo, in the units the design page itself works in:
 * scale relative to the fit, degrees, a mirror flag, and the shift as a share
 * of the window's own size — so the numbers hold whatever the design is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->json('photo_frames')->nullable()->after('photo_labels'));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('photo_frames'));
    }
};
