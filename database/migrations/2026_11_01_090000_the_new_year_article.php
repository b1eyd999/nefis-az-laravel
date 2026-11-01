<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The New Year article, in all three languages, with the shop's own
 * «YENİ İL» designs attached to it.
 *
 * Same rules as the first six: an article whose slug is already there is
 * left exactly as it is, so a second deploy — or the owner editing it in
 * the admin meanwhile — changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $products = DB::table('products')->pluck('id', 'slug');

        foreach (require database_path('data/blog-2026-11-yeni-il.php') as $article) {
            if (DB::table('posts')->where('slug', $article['slug'])->exists()) {
                continue;
            }

            $id = $this->insert($article, 'az', null, $article['date'], $article['topic'], $article['emoji']);

            // Slug prefixes, not slugs: `kinder-yeni-il` is the three Kinder
            // ones and `alyonka-yn` the two Alyonka ones — the whole category
            // as it stands, and whatever is left of it later.
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
        $slugs = [];

        foreach (require database_path('data/blog-2026-11-yeni-il.php') as $article) {
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
