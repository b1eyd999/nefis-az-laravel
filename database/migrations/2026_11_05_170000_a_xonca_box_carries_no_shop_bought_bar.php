<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A design may be a box with no shop-bought bar inside it.
 *
 * Every design the shop has ever sold is a printed box with a chocolate bar
 * the customer picks, and the price on the catalogue card is the design plus
 * the cheapest bar — which is why a 1.50 ₼ xonça box was offered at
 * «5.14 ₼-dan» the moment it went on the shelf.
 *
 * A xonça chocolate is not that. It is a small sweet in a printed box, forty
 * or eighty of them on one tray, and the owner's own figure is 1.50 ₼ each,
 * twenty for 30 ₼. A retail bar at three and a half manat cannot be in it.
 *
 * So a design may say it carries no bar. Then the page does not ask for one,
 * the basket does not add one, and the card says what the owner says it
 * says. Every design that existed before today carries one, as it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('needs_chocolate')->default(true)->after('max_quantity');
        });

        DB::table('products')->where('category', 'xonca')->update(['needs_chocolate' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('needs_chocolate');
        });
    }
};
