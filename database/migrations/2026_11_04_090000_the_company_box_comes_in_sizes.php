<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mini chocolate a company puts its logo on now comes in more than one
 * size, and the company picks the size on the page.
 *
 * Until today the size was a sentence — «4,5 × 9 × 1,5 sm» typed into the
 * page's settings — and the box drawn beside it was 45 × 90 mm, written into
 * the script. A size is a row now: its own proportions, its own picture, and
 * its own price ladder.
 *
 * What a size does NOT carry is the minimum order and the making time: the
 * owner says those are the same whatever the size, so they stay on the page
 * where he already edits them. Only the price differs.
 *
 * `front_image` / `back_image` are his own renders. They are optional on
 * purpose: with none the page draws the box the way it always has, in the
 * colour the visitor picked, so the sizes work the day they are added and
 * get better when the renders arrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // The Russian and English name, like a design's: {"ru": {"name": …}}
            $table->json('i18n')->nullable();
            // What the chip on the page says: «4,5 × 9 × 1,5 sm — bir dilim şokolad»
            $table->string('dimensions');
            /* The printed face in millimetres. Only the two of them matter —
               they are what the drawing is shaped by — but they are kept as
               millimetres rather than a ratio so the owner types what he
               measures. */
            $table->unsignedSmallInteger('width_mm');
            $table->unsignedSmallInteger('height_mm');
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            /* Where the printed face sits inside those renders, as percentages
               of the picture. 0/0/100/100 — the whole of it — is right for a
               render cropped to the face and wrong for one with a shadow
               around the box, which is why it is asked rather than assumed.
               Stored as a percentage so a re-render at another resolution
               does not move it. */
            $table->decimal('face_left', 5, 2)->default(0);
            $table->decimal('face_top', 5, 2)->default(0);
            $table->decimal('face_right', 5, 2)->default(100);
            $table->decimal('face_bottom', 5, 2)->default(100);
            // [{from: 100, price: 1.20}, …]; empty falls back to the page's own.
            $table->json('ladder')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('corporate_requests', function (Blueprint $table) {
            // Which size was asked for. Null for every request made before
            // today, and for one sent while no size is on the page at all.
            $table->foreignId('box_size_id')->nullable()->after('quantity')
                ->constrained('box_sizes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('corporate_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('box_size_id');
        });

        Schema::dropIfExists('box_sizes');
    }
};
