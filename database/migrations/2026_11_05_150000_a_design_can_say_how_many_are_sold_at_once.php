<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many of one design may be bought at once, when the design itself
 * decides.
 *
 * The basket has always stopped at twenty: a mistyped number should not
 * become a four-hundred-box order. But a xonça is a tray carried to an
 * engagement with dozens of small chocolates on it — twenty is where that
 * order *starts*, and fifty or eighty is ordinary. One cap for the whole
 * shop cannot be right for both.
 *
 * So a design may name its own floor and ceiling. Null on both is every
 * design the shop has sold until today: the basket's own twenty, and one
 * box if you only want one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // The fewest that may be ordered: a xonça is not sold by the box.
            $table->unsignedSmallInteger('min_quantity')->nullable()->after('price');
            // And the most, where the design can bear more than the usual 20.
            $table->unsignedSmallInteger('max_quantity')->nullable()->after('min_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['min_quantity', 'max_quantity']);
        });
    }
};
