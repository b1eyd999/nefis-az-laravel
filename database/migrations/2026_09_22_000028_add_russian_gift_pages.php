<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Russian versions of the gift-idea pages (/podarki/...). Plenty of people in
 * Baku look for gifts in Russian. Each Russian page is tied to its
 * Azerbaijani twin, so search engines know they are the same page in two
 * languages, and it shows the designs the owner picked on that twin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gift_pages', 'locale')) {
            Schema::table('gift_pages', function (Blueprint $table) {
                $table->string('locale', 5)->default('az')->after('slug');
                $table->string('link_text')->nullable()->after('menu_label');
                $table->foreignId('alt_of')->nullable()->after('locale')->constrained('gift_pages')->nullOnDelete();
            });
        }

        if (DB::table('gift_pages')->where('locale', 'ru')->exists()) {
            return;
        }

        $twins = DB::table('gift_pages')->where('locale', 'az')->pluck('id', 'slug');

        foreach (require database_path('data/gift-pages-ru.php') as $i => $page) {
            $twin = $twins[$page['alt_of']] ?? null;

            // No designs of its own: it shows whatever the owner picked on the
            // Azerbaijani twin, so the catalogue is kept in one place.
            DB::table('gift_pages')->insert([
                'slug' => $page['slug'],
                'locale' => 'ru',
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
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('gift_pages')->where('locale', 'ru')->delete();
    }
};
