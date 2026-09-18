<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears the whole design catalogue: the owner is starting over with a new
 * way of producing designs.
 *
 * Orders must survive it. Each order line keeps the name of what was bought,
 * and its link to the product is allowed to go empty instead of blocking the
 * delete. The artwork itself stays on Yandex Disk and on the owner's machine;
 * only the catalogue rows go. There is no way back from here except the
 * database backup taken before the deploy, so down() only restores the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
        });

        DB::statement(
            'UPDATE order_items SET product_name = '
            . '(SELECT name FROM products WHERE products.id = order_items.product_id)'
        );

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        // Slots belong to products and their angles only, so all of them go.
        DB::table('photo_slots')->delete();
        DB::table('text_slots')->delete();
        DB::table('product_angles')->delete();
        DB::table('products')->delete();
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
