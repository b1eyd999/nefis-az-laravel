<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plain shapes drawn into a design: a band behind the wording, a rule under a
 * name, a circle, a heart.
 *
 * Until now the only way to put a coloured rectangle on a box was to draw it
 * in Photoshop, export a transparent PNG and upload it as a layer — and to do
 * that again for every change of colour or size. A shape is the same thing
 * without the round trip: it scales without blurring, weighs nothing, and its
 * colour is a field rather than a new file.
 *
 * They sit above or below the customer's photo, exactly as image layers do.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_shapes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('kind', 12)->default('rect');
            $t->integer('x')->default(0);
            $t->integer('y')->default(0);
            $t->integer('width')->default(100);
            $t->integer('height')->default(100);
            $t->integer('rotation')->default(0);
            // Null means the shape is not filled at all — an outline only.
            $t->string('fill', 9)->nullable();
            $t->string('stroke_color', 9)->nullable();
            $t->decimal('stroke_width', 5, 1)->default(0);
            $t->unsignedSmallInteger('radius')->default(0);
            $t->unsignedTinyInteger('opacity')->default(100);
            $t->string('placement', 8)->default('above');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_shapes');
    }
};
