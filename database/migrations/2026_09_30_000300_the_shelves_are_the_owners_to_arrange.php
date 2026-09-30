<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The catalogue's shelves become the owner's to arrange.
 *
 * Until now the five categories were a list inside the code: he could file a
 * design under one of them, but not add a sixth, rename one or change the
 * order the shelves stand in — that took a deploy. Worse, the page walked that
 * list rather than the designs, so a design filed under anything else was
 * counted in the total and then never drawn.
 *
 * The designs keep the key they already hold (`products.category` stays a
 * string), so nothing has to be rewritten and a typo in the admin cannot
 * orphan a product. This only gives those keys a table to live in.
 */
return new class extends Migration
{
    /** The shelves the shop grew up with, in the order they stood. */
    private const KNOWN = [
        'sokolad' => 'Şokolad Dizaynları',
        'poster' => 'Posterlər',
        'love-is' => 'Love is...',
        'xerite' => 'Xəritə Posterləri',
        'spotify' => 'Spotify Posterləri',
    ];

    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
            $table->json('i18n')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $rows = [];
        $order = 0;
        foreach (self::KNOWN as $slug => $name) {
            $rows[] = ['slug' => $slug, 'name' => $name, 'is_active' => true,
                'sort_order' => $order++, 'created_at' => $now, 'updated_at' => $now];
        }

        // Whatever the designs are actually filed under gets a shelf too, even
        // a key nobody remembers writing: those designs were invisible in the
        // catalogue, and after this they are not.
        if (Schema::hasTable('products')) {
            $used = DB::table('products')->whereNotNull('category')->where('category', '!=', '')
                ->distinct()->pluck('category');

            foreach ($used as $slug) {
                if (! array_key_exists($slug, self::KNOWN)) {
                    $rows[] = ['slug' => mb_substr((string) $slug, 0, 40), 'name' => mb_substr((string) $slug, 0, 80),
                        'is_active' => true, 'sort_order' => $order++, 'created_at' => $now, 'updated_at' => $now];
                }
            }
        }

        DB::table('product_categories')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
