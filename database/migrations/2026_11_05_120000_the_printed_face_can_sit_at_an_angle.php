<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The printed face stops being a rectangle.
 *
 * A size's render was marked with four percentages — left, top, right,
 * bottom — which can only describe a box photographed dead square on. The
 * owner renders his boxes turned and tilted, the way a box is actually
 * looked at, and on those the printed face is a quadrilateral: four corners
 * that are not aligned to anything.
 *
 * So the face is four points now, clockwise from its top left, each a
 * fraction of the picture. Null means he has not placed them, and the four
 * old percentages still answer — which is what every size has until he
 * drags the corners, and what the page keeps drawing meanwhile.
 *
 * The page already owns the warper this needs: it is the one that lays a
 * company's logo onto the photograph of a café counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('box_sizes', function (Blueprint $table) {
            // [[x, y], [x, y], [x, y], [x, y]] — fractions, clockwise.
            $table->json('face_quad')->nullable()->after('face_bottom');
        });
    }

    public function down(): void
    {
        Schema::table('box_sizes', function (Blueprint $table) {
            $table->dropColumn('face_quad');
        });
    }
};
