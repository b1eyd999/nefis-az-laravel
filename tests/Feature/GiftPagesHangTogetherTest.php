<?php

namespace Tests\Feature;

use App\Models\GiftPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The gift-idea pages, and the two columns that quietly decide whether they
 * work at all.
 *
 * `alt_of` is what ties a Russian or English page to its Azerbaijani twin.
 * hreflang is built from nothing else, so a page without it ships as a
 * stranger in three languages — exactly the duplicate-alternate problem
 * these pages exist to avoid.
 *
 * `link_text` is how every other page links to this one. Left empty, the
 * shop falls back to Azerbaijani grammar — menu_label plus «hədiyyəsi» —
 * for every language, and the Russian pages start printing «Необычный
 * подарок hədiyyəsi». On an Azerbaijani page that fallback is correct, and
 * thirteen of them rely on it; on any other language it is a fault.
 *
 * Both were missed on the first draft of three new pages, which is why this
 * is written down.
 */
class GiftPagesHangTogetherTest extends TestCase
{
    use RefreshDatabase;

    /** The three pages added in October, in all three languages. */
    public static function theOctoberThree(): array
    {
        return [
            'an unusual gift' => ['qeyri-adi-hediyye', 'neobychnyy-podarok', 'unusual-gift'],
            'at the last moment' => ['son-deqiqe-hediyyesi', 'podarok-v-posledniy-moment', 'last-minute-gift'],
            'New Year for a company' => ['korporativ-yeni-il', 'korporativnye-na-novyy-god', 'corporate-new-year-gifts'],
        ];
    }

    /** Every page that is not Azerbaijani is tied to the one that is. */
    public function test_every_translation_knows_its_twin(): void
    {
        $orphans = GiftPage::where('locale', '!=', 'az')->whereNull('alt_of')->pluck('slug');

        $this->assertSame([], $orphans->all(),
            'without alt_of these ship as unlinked pages and hreflang never names them');

        foreach (GiftPage::where('locale', '!=', 'az')->get() as $page) {
            $twin = GiftPage::find($page->alt_of);
            $this->assertNotNull($twin, $page->slug . ' points at a page that is gone');
            $this->assertSame('az', $twin->locale, $page->slug . ' must hang off the Azerbaijani page');
        }
    }

    /**
     * A Russian or English page says in its own language how it is linked
     * to. Azerbaijani may leave it empty: there the fallback is right.
     */
    public function test_no_russian_or_english_page_borrows_azerbaijani_grammar(): void
    {
        $wrong = [];
        foreach (GiftPage::where('locale', '!=', 'az')->get() as $page) {
            if (blank($page->link_text)) {
                $wrong[] = $page->locale . '/' . $page->slug . ' — no link_text';

                continue;
            }
            if (str_contains($page->linkText(), 'hədiyyə')) {
                $wrong[] = $page->locale . '/' . $page->slug . ' — «' . $page->linkText() . '»';
            }
        }

        $this->assertSame([], $wrong, "these would print Azerbaijani inside another language:\n"
            . implode("\n", $wrong));
    }

    /**
     * @dataProvider theOctoberThree
     */
    public function test_each_of_the_three_opens_in_every_language(string $az, string $ru, string $en): void
    {
        $pages = [
            'az' => GiftPage::where('slug', $az)->first(),
            'ru' => GiftPage::where('slug', $ru)->first(),
            'en' => GiftPage::where('slug', $en)->first(),
        ];

        foreach ($pages as $locale => $page) {
            $this->assertNotNull($page, $locale . ': ' . $az . ' was never written');
            $this->assertSame($locale, $page->locale);
            $this->assertTrue((bool) $page->is_active);
            $this->assertNotEmpty($page->body);
            $this->assertNotEmpty($page->faq, 'a gift page without questions answers none');
        }

        $this->assertSame($pages['az']->id, $pages['ru']->alt_of);
        $this->assertSame($pages['az']->id, $pages['en']->alt_of);

        foreach ([
            '/hediyye/' . $az,
            '/ru/podarki/' . $ru,
            '/en/gifts/' . $en,
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }

    /**
     * Each page names its own designs.
     *
     * They are attached when the migration runs, from the slugs in
     * database/data/gift-pages-2026-10.php — so what is checked here is
     * that file, which is the part a person can get wrong. A page with no
     * designs falls back to the whole catalogue, and every one of these
     * pages says in its own text that it is showing a chosen few.
     */
    public function test_each_of_the_three_names_the_designs_it_shows(): void
    {
        $topics = require database_path('data/gift-pages-2026-10.php');

        $this->assertCount(3, $topics);

        foreach ($topics as $topic) {
            $slugs = $topic['products'] ?? [];
            $this->assertGreaterThanOrEqual(4, count($slugs),
                $topic['slug'] . ' shows the whole catalogue instead of designs it chose');
            $this->assertSame(array_values(array_unique($slugs)), array_values($slugs),
                $topic['slug'] . ' names the same design twice');

            foreach ($slugs as $slug) {
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug,
                    $topic['slug'] . ': «' . $slug . '» is not a design address');
            }

            // Both translations are there, and under their own addresses.
            foreach (['ru', 'en'] as $locale) {
                $this->assertArrayHasKey($locale, $topic, $topic['slug'] . ' has no ' . $locale);
                $this->assertNotSame($topic['slug'], $topic[$locale]['slug']);
                $this->assertNotEmpty($topic[$locale]['link_text'],
                    $locale . '/' . $topic[$locale]['slug'] . ' would borrow Azerbaijani grammar');
            }
        }
    }

    /** What the shop promises on these pages, it must be able to keep. */
    public function test_the_three_promise_nothing_the_shop_has_not_said(): void
    {
        $banned = [
            'dekabrın ortasına' => 'a mid-December cut-off nobody stated',
            'busiest month' => 'which month is busiest is not ours to claim',
            'страница предупредит' => 'there is no resolution warning on the page',
            'Перед печатью мы смотрим' => 'no pre-print check is promised for personalised boxes',
            'bizdəndir' => 'free delivery was never offered for the mini chocolate',
            'за наш счёт' => 'the same, in Russian',
            'ten boxes' => 'a minimum the other two languages of the same page deny',
        ];

        foreach (GiftPage::whereIn('slug', [
            'qeyri-adi-hediyye', 'neobychnyy-podarok', 'unusual-gift',
            'son-deqiqe-hediyyesi', 'podarok-v-posledniy-moment', 'last-minute-gift',
            'korporativ-yeni-il', 'korporativnye-na-novyy-god', 'corporate-new-year-gifts',
        ])->get() as $page) {
            $all = $page->body . ' ' . $page->intro . ' ' . $page->meta_description
                . ' ' . json_encode($page->faq, JSON_UNESCAPED_UNICODE);

            foreach ($banned as $phrase => $why) {
                $this->assertStringNotContainsString($phrase, $all,
                    $page->locale . '/' . $page->slug . ': ' . $why);
            }
        }
    }
}
