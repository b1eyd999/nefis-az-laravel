<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gift-idea pages for search engines (/hediyye/ad-gunu, /hediyye/sevgiliye…):
 * each answers one thing people search for, with its own words, questions and
 * the designs that suit it. The first ones come from database/data/gift-pages.php;
 * after that the owner writes them in the admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gift_pages')) {
            return;
        }

        Schema::create('gift_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('menu_label', 60);
            $table->string('emoji', 16)->nullable();
            $table->string('title');
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('eyebrow')->nullable();
            $table->text('intro')->nullable();
            $table->longText('body')->nullable();
            $table->json('faq')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('gift_page_product', function (Blueprint $table) {
            $table->foreignId('gift_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['gift_page_id', 'product_id']);
        });

        $products = DB::table('products')->pluck('id', 'slug');

        foreach (require database_path('data/gift-pages.php') as $i => $page) {
            $id = DB::table('gift_pages')->insertGetId([
                'slug' => $page['slug'],
                'menu_label' => $page['menu_label'],
                'emoji' => $page['emoji'],
                'title' => $page['title'],
                'meta_title' => $page['meta_title'],
                'meta_description' => $page['meta_description'],
                'eyebrow' => $page['eyebrow'],
                'intro' => $page['intro'],
                'body' => $page['body'],
                'faq' => json_encode($page['faq'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $rows = collect($page['products'])
                ->map(fn (string $slug) => $products[$slug] ?? null)
                ->filter()
                ->unique()
                ->map(fn (int $productId) => ['gift_page_id' => $id, 'product_id' => $productId]);
            DB::table('gift_page_product')->insert($rows->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_page_product');
        Schema::dropIfExists('gift_pages');
    }
};
