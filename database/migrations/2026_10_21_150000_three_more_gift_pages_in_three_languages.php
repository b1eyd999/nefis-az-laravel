<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Three gift-idea pages the shop did not have, in all three languages.
 *
 * An unusual gift, a gift at the last moment, and New Year for a company.
 * Each Azerbaijani page carries its own designs; the Russian and English
 * ones hang off it through `alt_of`, which is what builds hreflang and what
 * lets a translation borrow the Azerbaijani page's designs.
 *
 * A first draft of these was written and refused: it promised things the
 * shop never said — free delivery, an approved mock-up, a day it would be
 * ready — and the Azerbaijani read as a translation. What is here is the
 * rewrite, shorter, and with nothing in it the site does not already say.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = require database_path('data/gift-pages-2026-10.php');
        $sort = (int) DB::table('gift_pages')->max('sort_order');
        $products = DB::table('products')->pluck('id', 'slug');

        foreach ($pages as $page) {
            // Said again on a second deploy, or added by hand in the admin
            // meanwhile: leave whatever is there alone.
            if (DB::table('gift_pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            $sort++;
            $id = $this->insert($page, 'az', $sort, null);

            $rows = collect($page['products'] ?? [])
                ->map(fn (string $slug) => $products[$slug] ?? null)
                ->filter()->unique()
                ->map(fn (int $productId) => ['gift_page_id' => $id, 'product_id' => $productId]);
            if ($rows->isNotEmpty()) {
                DB::table('gift_page_product')->insert($rows->values()->all());
            }

            foreach (['ru', 'en'] as $locale) {
                if (empty($page[$locale]) || DB::table('gift_pages')->where('slug', $page[$locale]['slug'])->exists()) {
                    continue;
                }
                // The translation shows the same designs through its twin.
                $this->insert($page[$locale] + ['emoji' => $page['emoji']], $locale, $sort, $id);
            }
        }
    }

    private function insert(array $page, string $locale, int $sort, ?int $twin): int
    {
        return DB::table('gift_pages')->insertGetId([
            'slug' => $page['slug'],
            'locale' => $locale,
            'alt_of' => $twin,
            'menu_label' => $page['menu_label'],
            /* Without this the shop builds the link in Azerbaijani grammar
               for every language and prints «Необычный подарок hədiyyəsi». */
            'link_text' => $page['link_text'],
            'emoji' => $page['emoji'],
            'title' => $page['title'],
            'meta_title' => $page['meta_title'],
            'meta_description' => $page['meta_description'],
            'eyebrow' => $page['eyebrow'],
            'intro' => $page['intro'],
            'body' => $page['body'],
            'faq' => json_encode($page['faq'], JSON_UNESCAPED_UNICODE),
            'is_active' => true,
            'sort_order' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $slugs = collect(require database_path('data/gift-pages-2026-10.php'))
            ->flatMap(fn (array $p) => array_filter([
                $p['slug'], $p['ru']['slug'] ?? null, $p['en']['slug'] ?? null,
            ]))->all();

        $ids = DB::table('gift_pages')->whereIn('slug', $slugs)->pluck('id');
        DB::table('gift_page_product')->whereIn('gift_page_id', $ids)->delete();
        DB::table('gift_pages')->whereIn('id', $ids)->delete();
    }
};
