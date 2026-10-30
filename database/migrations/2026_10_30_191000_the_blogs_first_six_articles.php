<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The blog's first six articles, in all three languages.
 *
 * Each Azerbaijani article is the original; the Russian and English ones hang
 * off it through `alt_of`, which is what hreflang is built from and what lets
 * a translation borrow the Azerbaijani article's designs.
 *
 * Said again on a second deploy, or edited by hand in the admin meanwhile:
 * an article whose slug is already there is left exactly as it is. Nothing
 * here ever overwrites the owner's own words.
 */
return new class extends Migration
{
    public function up(): void
    {
        $articles = array_merge(
            require database_path('data/blog-2026-10-a.php'),
            require database_path('data/blog-2026-10-b.php'),
            require database_path('data/blog-2026-10-c.php'),
        );

        $products = DB::table('products')->pluck('id', 'slug');

        foreach ($articles as $article) {
            if (DB::table('posts')->where('slug', $article['slug'])->exists()) {
                continue;
            }

            $id = $this->insert($article, 'az', null, $article['date'], $article['topic'], $article['emoji']);

            /* The designs are named by slug prefix rather than by slug: the
               catalogue is rearranged often enough that exact names would
               attach the wrong designs, or silently none. Three per prefix,
               so an article naming two families shows both rather than six
               of whichever happens to sort first. */
            $rows = collect($article['designs'] ?? [])
                ->flatMap(fn (string $prefix) => $products
                    ->filter(fn (int $productId, string $slug) => str_starts_with($slug, $prefix))
                    ->take(3)->values())
                ->unique()
                ->map(fn (int $productId) => ['post_id' => $id, 'product_id' => $productId]);

            if ($rows->isNotEmpty()) {
                DB::table('post_product')->insert($rows->values()->all());
            }

            foreach (['ru', 'en'] as $locale) {
                if (empty($article[$locale])
                    || DB::table('posts')->where('slug', $article[$locale]['slug'])->exists()) {
                    continue;
                }

                // A translation shows its twin's designs, so it needs no list.
                $this->insert($article[$locale], $locale, $id, $article['date'],
                    $article[$locale]['topic'] ?? $article['topic'], $article['emoji']);
            }
        }
    }

    private function insert(array $article, string $locale, ?int $twin, string $date, ?string $topic, ?string $emoji): int
    {
        return DB::table('posts')->insertGetId([
            'slug' => $article['slug'],
            'locale' => $locale,
            'alt_of' => $twin,
            /* The topic is a word on a chip, so it is the reader's language,
               not Azerbaijani on a Russian page. Where a translation does not
               give one, the Azerbaijani word stands rather than an empty
               chip — the owner can change it in the admin. */
            'topic' => $topic,
            'emoji' => $emoji,
            'title' => $article['title'],
            'meta_title' => $article['meta_title'],
            'meta_description' => $article['meta_description'],
            'excerpt' => $article['excerpt'],
            'body' => $article['body'],
            'faq' => json_encode($article['faq'], JSON_UNESCAPED_UNICODE),
            'published_at' => $date,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $articles = array_merge(
            require database_path('data/blog-2026-10-a.php'),
            require database_path('data/blog-2026-10-b.php'),
            require database_path('data/blog-2026-10-c.php'),
        );

        $slugs = [];
        foreach ($articles as $article) {
            $slugs[] = $article['slug'];
            foreach (['ru', 'en'] as $locale) {
                if (! empty($article[$locale])) {
                    $slugs[] = $article[$locale]['slug'];
                }
            }
        }

        DB::table('posts')->whereIn('slug', $slugs)->delete();
    }
};
