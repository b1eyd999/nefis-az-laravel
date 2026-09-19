<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chocolate bar the customer picks to go inside their box.
 *
 * Bars are imported from Araz Market's "Plitka şokolad" category (90-105 g)
 * with their regular and promotional prices, or added by hand. The price a
 * customer pays is the source price plus the owner's markup — one percentage
 * for all bars (kept in `settings`), which a single bar may override.
 *
 * An order line keeps the bar's name and price as they were when ordered.
 *
 * Rerun-safe: production is MySQL, which keeps DDL that ran before a failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('chocolates')) {
            Schema::create('chocolates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->decimal('weight_g', 6, 1)->nullable();
                $table->string('image')->nullable();
                $table->decimal('base_price', 8, 2);            // the source's regular price
                $table->decimal('sale_price', 8, 2)->nullable(); // the source's promotional price
                $table->unsignedTinyInteger('sale_percent')->nullable();
                $table->unsignedSmallInteger('markup_percent')->nullable(); // null = the common markup
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->string('source', 30)->nullable();       // 'arazmarket', or null when added by hand
                $table->string('source_id', 40)->nullable();
                $table->string('source_url')->nullable();
                $table->string('barcode', 40)->nullable();
                $table->boolean('in_source')->default(true);
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
                $table->unique(['source', 'source_id']);
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        foreach ([
            'chocolate_id' => fn (Blueprint $t) => $t->unsignedBigInteger('chocolate_id')->nullable()->index(),
            'chocolate_name' => fn (Blueprint $t) => $t->string('chocolate_name')->nullable(),
            'chocolate_price' => fn (Blueprint $t) => $t->decimal('chocolate_price', 8, 2)->nullable(),
        ] as $column => $add) {
            if (! Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', $add);
            }
        }
    }

    public function down(): void
    {
        foreach (['chocolate_id', 'chocolate_name', 'chocolate_price'] as $column) {
            if (Schema::hasColumn('order_items', $column)) {
                Schema::table('order_items', function (Blueprint $t) use ($column) {
                    if ($column === 'chocolate_id') {
                        $t->dropIndex(['chocolate_id']);
                    }
                    $t->dropColumn($column);
                });
            }
        }
        Schema::dropIfExists('settings');
        Schema::dropIfExists('chocolates');
    }
};
