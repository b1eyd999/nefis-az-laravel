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
 * delete. The artwork itself stays on Yandex Disk and on the owner's machine.
 *
 * The catalogue rows are copied into *_backup_20260918 tables first, in the
 * same database, so the delete can be undone without a download. Drop those
 * tables once the new catalogue is settled.
 *
 * MySQL does not roll schema changes back when a migration fails halfway, so
 * every step checks whether it already happened.
 */
return new class extends Migration
{
    private const TABLES = ['products', 'product_angles', 'photo_slots', 'text_slots'];

    private const SUFFIX = '_backup_20260918';

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table . self::SUFFIX)) {
                DB::statement("CREATE TABLE {$table}" . self::SUFFIX . " AS SELECT * FROM {$table}");
            }
        }

        if (! Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('product_name')->nullable()->after('product_id');
            });
        }

        DB::statement(
            'UPDATE order_items SET product_name = '
            . '(SELECT name FROM products WHERE products.id = order_items.product_id) '
            . 'WHERE product_name IS NULL AND product_id IS NOT NULL'
        );

        $this->relaxProductLink();

        // Slots belong to products and their angles only, so all of them go.
        DB::table('photo_slots')->delete();
        DB::table('text_slots')->delete();
        DB::table('product_angles')->delete();
        DB::table('products')->delete();
    }

    public function down(): void
    {
        // Restoring the catalogue means copying the backup tables back by hand.
        if (Schema::hasColumn('order_items', 'product_name')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('product_name');
            });
        }
    }

    /**
     * Lets an order line outlive its product: the link may be empty, and
     * deleting a product empties it rather than being refused.
     */
    private function relaxProductLink(): void
    {
        $foreignKeys = collect(Schema::getForeignKeys('order_items'));
        $current = $foreignKeys->first(fn ($fk) => $fk['columns'] === ['product_id']);

        if ($current && $current['on_delete'] === 'set null') {
            return;
        }

        if ($current) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
            });
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }
};
