<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The twelve Azerbaijani gift pages that had no English twin.
 *
 * Seven were written when English first went up; with these, every page the
 * shop has in Azerbaijani can be read in English too — which is the point of
 * turning the language on at all. Each is tied to its twin, so the designs
 * are still picked once, on the Azerbaijani side.
 *
 * A page whose slug already exists is left alone, so running this again
 * cannot overwrite a word the owner has since changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $twins = DB::table('gift_pages')->where('locale', 'az')->pluck('id', 'slug');
        $last = (int) DB::table('gift_pages')->max('sort_order');

        foreach (require database_path('data/gift-pages-en2.php') as $i => $page) {
            if (DB::table('gift_pages')->where('locale', 'en')->where('slug', $page['slug'])->exists()) {
                continue;
            }

            // Without its Azerbaijani twin the page would show no designs at
            // all, so it waits rather than going up empty.
            if (! isset($twins[$page['alt_of']])) {
                continue;
            }

            DB::table('gift_pages')->insert([
                'slug' => $page['slug'],
                'locale' => 'en',
                'alt_of' => $twins[$page['alt_of']],
                'menu_label' => $page['menu_label'],
                'link_text' => $page['link_text'],
                'emoji' => $page['emoji'],
                'title' => $page['title'],
                'meta_title' => $page['meta_title'],
                'meta_description' => $page['meta_description'],
                'eyebrow' => $page['eyebrow'],
                'intro' => $page['intro'],
                'body' => $page['body'],
                'faq' => json_encode($page['faq'] ?? [], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'sort_order' => $page['sort_order'] ?? ($last + $i + 1),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $slugs = array_column(require database_path('data/gift-pages-en2.php'), 'slug');
        DB::table('gift_pages')->where('locale', 'en')->whereIn('slug', $slugs)->delete();
    }
};
