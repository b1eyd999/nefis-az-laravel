<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A shelf of pictures the box editor can reach from any design: frames,
 * patterns, stickers.
 *
 * Until now every layer belonged to the box it was uploaded onto, so the same
 * gold frame had to be found on the computer and uploaded again for each new
 * design. This is where it is kept once. Putting one on a box still copies the
 * file into that box's own folder — a design must not depend on a picture the
 * owner may later delete from the shelf.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('category', 20)->default('other');
            $table->string('image');
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The editor asks for one category at a time, in the owner's order.
            $table->index(['is_active', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_assets');
    }
};
