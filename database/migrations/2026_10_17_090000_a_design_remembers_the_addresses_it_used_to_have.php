<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every address a design has ever been published at.
 *
 * The owner renames a design in the admin — «Alyonka Azerbaijan style»
 * becomes «Alyonka Aze Sytle Vol 1» — and the slug follows the name. The
 * design is still there, still for sale, but its old address answers 404.
 * Google had it indexed, people had it in messages and on Instagram, and all
 * of it lands on an error page. Search Console then lists the dead address,
 * which is why that report keeps refilling after it is cleared.
 *
 * So the old address is kept here and redirected to the new one. Permanently
 * — a 301 — because that is what tells a search engine to carry the old
 * page's standing over to the new address rather than start again.
 *
 * A row is deleted when its slug is taken back into use, so the table can
 * never point a design at itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_slugs')) {
            return;
        }

        Schema::create('product_slugs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            /* Unique across the whole shop, not per design: an address can
               only ever lead to one place, and a slug freed by one design
               and taken by another must belong to whoever holds it now. */
            $table->string('slug')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_slugs');
    }
};
