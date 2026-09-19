<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer-facing mockups the owner builds in the scene editor.
 *
 * scene_assets is the shared library (backgrounds and rendered empty boxes,
 * uploaded once, used in any scene); a scene stacks them and marks where the
 * design is corner-pinned. product_scene optionally narrows a product to some
 * scenes; with none picked it shows in all of them.
 *
 * Every step checks first: production is MySQL, which keeps whatever DDL ran
 * before a failure, and there is no shell to tidy up by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scene_assets')) {
            Schema::create('scene_assets', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('kind', 20); // background | object
                $table->string('image');
                $table->unsignedInteger('width');
                $table->unsignedInteger('height');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('scenes')) {
            Schema::create('scenes', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('background')->nullable();
                $table->string('background_color', 9)->nullable();
                $table->unsignedInteger('width')->default(1600);
                $table->unsignedInteger('height')->default(2000);
                $table->json('elements')->nullable();
                $table->string('preview_image')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // The colour a product's box is dyed in every scene whose render
        // follows it (white renders take any colour).
        if (! Schema::hasColumn('products', 'box_color')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('box_color', 9)->nullable()->after('preview_image');
            });
        }

        if (! Schema::hasTable('product_scene')) {
            Schema::create('product_scene', function (Blueprint $table) {
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
                $table->primary(['product_id', 'scene_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_scene');
        if (Schema::hasColumn('products', 'box_color')) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn('box_color'));
        }
        Schema::dropIfExists('scenes');
        Schema::dropIfExists('scene_assets');
    }
};
