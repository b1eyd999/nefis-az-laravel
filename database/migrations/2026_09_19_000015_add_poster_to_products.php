<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A catalogue poster per product: a finished photo the owner shares from
 * Yandex Disk, kept here as a small WebP.
 *
 * The first poster link went into the slug field, which broke that product's
 * page address; such slugs are moved into the new field and rebuilt from the
 * name. `products:fetch-posters` (run on deploy) then brings the photo in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'poster_url')) {
                $table->string('poster_url', 500)->nullable()->after('preview_image');
            }
            if (! Schema::hasColumn('products', 'poster_image')) {
                $table->string('poster_image')->nullable()->after('poster_url');
            }
        });

        foreach (DB::table('products')->where('slug', 'like', '%/%')->get(['id', 'name', 'slug', 'poster_url']) as $product) {
            $base = Str::slug($product->name) ?: 'mehsul-' . $product->id;
            $slug = $base;
            for ($n = 2; DB::table('products')->where('slug', $slug)->where('id', '!=', $product->id)->exists(); $n++) {
                $slug = $base . '-' . $n;
            }

            $isYandex = (bool) preg_match('#^https?://(disk\.yandex\.[a-z.]+|yadi\.sk)/#i', $product->slug);
            DB::table('products')->where('id', $product->id)->update([
                'slug' => $slug,
                'poster_url' => $product->poster_url ?: ($isYandex ? $product->slug : null),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['poster_url', 'poster_image']);
        });
    }
};
