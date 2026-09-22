<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Six more gift-idea pages in both languages: the product search itself
 * ("şəkilli şokolad" / "шоколад с фото"), teachers, Novruz, companies,
 * weddings and colleagues. Each Russian page is tied to its Azerbaijani twin.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pages = require database_path('data/gift-pages-wave2.php');
        $sort = (int) DB::table('gift_pages')->max('sort_order');
        $products = DB::table('products')->pluck('id', 'slug');

        foreach ($pages as $page) {
            if (DB::table('gift_pages')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            $sort++;
            $id = $this->insert($page, 'az', ++$sort, null);

            $rows = collect($page['products'])
                ->map(fn (string $slug) => $products[$slug] ?? null)
                ->filter()
                ->unique()
                ->map(fn (int $productId) => ['gift_page_id' => $id, 'product_id' => $productId]);
            DB::table('gift_page_product')->insert($rows->values()->all());

            // The Russian page shows the same designs through its twin.
            $this->insert($page['ru'] + ['emoji' => $page['emoji']], 'ru', $sort, $id);
        }
    }

    private function insert(array $page, string $locale, int $sort, ?int $twin): int
    {
        return DB::table('gift_pages')->insertGetId([
            'slug' => $page['slug'],
            'locale' => $locale,
            'alt_of' => $twin,
            'menu_label' => $page['menu_label'],
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
        foreach (require database_path('data/gift-pages-wave2.php') as $page) {
            DB::table('gift_pages')->whereIn('slug', [$page['slug'], $page['ru']['slug']])->delete();
        }
    }
};
