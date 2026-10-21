<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ready package: one price, nothing to decide.
 *
 * The owner puts one together in the admin — this design, this chocolate,
 * this wrapping paper, the polaroid letter, the live photograph — gives the
 * lot a single price, and hangs it on a banner. The customer presses
 * «Sifariş et» on the banner and lands on the design page with all of it
 * already chosen: he adds his photograph and his words, and that is all.
 *
 * The parts are fixed on purpose. The price is agreed for the package as a
 * whole, so pulling the wrapping out of it would leave a discount with
 * nothing to discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            /* Nothing is required but the design: a package may be a box and
               a live photograph and no paper at all. */
            $table->foreignId('chocolate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('wrapping_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('letter_on')->default(false);
            $table->boolean('ar_on')->default(false);

            // What the whole package costs, for one.
            $table->decimal('price', 8, 2);

            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* And what an order remembers of it. The name and the price are kept
           beside the line, the way the wrapping already is: a package the
           owner renames or reprices next month must not rewrite what
           somebody paid for last month. */
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('bundle_id')->nullable()->after('product_name');
            $table->string('bundle_name')->nullable()->after('bundle_id');
            /* The figure that was agreed, frozen. Every other price on this
               line is frozen too, for the same reason: the owner reprices a
               package next month and what somebody paid last month must not
               move with it. While this is set, it IS the line's price — the
               parts beside it are what was in the package, not what it
               cost. */
            $table->decimal('bundle_price', 8, 2)->nullable()->after('bundle_name');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['bundle_id', 'bundle_name', 'bundle_price']);
        });
        Schema::dropIfExists('bundles');
    }
};
