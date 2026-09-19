<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting: what a box costs to make, what is left in stock, and the
 * profit the orders really bring.
 *
 * materials         stock items (box paper, glue, print…): bought in packs
 *                   (pack_price for pack_size units), `per_box` units go into
 *                   every box, `stock` units are on hand
 * stock_movements   every change to stock: a purchase (+, costs money), an
 *                   order's usage (−), a cancelled order's return (+), or a
 *                   count correction
 * expenses          other spending (ads, courier, rent…)
 * orders.materials_cost, order_items.chocolate_cost
 *                   what an order cost to make, as it was when ordered
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('materials')) {
            Schema::create('materials', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('unit', 30)->default('ədəd');
                $table->decimal('pack_price', 10, 2)->default(0);
                $table->decimal('pack_size', 10, 3)->default(1);
                $table->decimal('per_box', 10, 3)->default(0);
                $table->decimal('stock', 12, 3)->default(0);
                $table->decimal('low_stock', 12, 3)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('materials')->insert([
                ['name' => 'Qutu kağızı', 'unit' => 'vərəq', 'pack_price' => 9.80, 'pack_size' => 50, 'per_box' => 1, 'stock' => 0,
                    'low_stock' => 20, 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
                // A tube does about 50 boxes: counted in boxes' worth.
                ['name' => 'Yapışqan', 'unit' => 'qutuluq', 'pack_price' => 0.80, 'pack_size' => 50, 'per_box' => 1, 'stock' => 0,
                    'low_stock' => 10, 'is_active' => true, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('material_id')->index();
                $table->unsignedBigInteger('order_id')->nullable()->index();
                $table->string('type', 20);            // purchase | usage | return | adjust
                $table->decimal('quantity', 12, 3);    // + into stock, − out of it
                $table->decimal('unit_cost', 10, 4)->default(0);
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('expenses')) {
            Schema::create('expenses', function (Blueprint $table) {
                $table->id();
                $table->date('spent_on');
                $table->string('category', 60);
                $table->decimal('amount', 10, 2);
                $table->string('note')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('orders', 'materials_cost')) {
            Schema::table('orders', fn (Blueprint $t) => $t->decimal('materials_cost', 10, 2)->nullable());
        }
        if (! Schema::hasColumn('order_items', 'chocolate_cost')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->decimal('chocolate_cost', 8, 2)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'chocolate_cost')) {
            Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('chocolate_cost'));
        }
        if (Schema::hasColumn('orders', 'materials_cost')) {
            Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('materials_cost'));
        }
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('materials');
    }
};
