<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The English gift-idea pages (/en/gifts/...). Each one is tied to its
 * Azerbaijani twin, so it shows the designs the owner picked there and search
 * engines see one page in three languages.
 */
return new class extends Migration
{
    public function up(): void
    {
        $twins = DB::table('gift_pages')->where('locale', 'az')->pluck('id', 'slug');
        $last = (int) DB::table('gift_pages')->max('sort_order');

        foreach (require database_path('data/gift-pages-en.php') as $i => $page) {
            if (DB::table('gift_pages')->where('locale', 'en')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            DB::table('gift_pages')->insert([
                'slug' => $page['slug'],
                'locale' => 'en',
                'alt_of' => $twins[$page['alt_of']] ?? null,
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
                'sort_order' => $last + $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('gift_pages')->where('locale', 'en')->delete();
    }
};
