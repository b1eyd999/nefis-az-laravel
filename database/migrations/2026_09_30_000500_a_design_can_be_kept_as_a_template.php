<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A design kept aside to start the next one from.
 *
 * The boxes this shop sells look like one another: the same dark page, the
 * same frame, the same three captions, a different photo window. Drawing all
 * of it again for every design is the slowest part of the owner's evening, so
 * a finished design can be put on a shelf and laid on the next box whole.
 *
 * The whole design travels as JSON — artwork, shapes, windows, captions and
 * the box colour — exactly as the editor holds it. The pictures are copied
 * into the new box when the template is used, never shared, so deleting a
 * template or the design it came from cannot empty a box that is on sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('preview')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_templates');
    }
};
