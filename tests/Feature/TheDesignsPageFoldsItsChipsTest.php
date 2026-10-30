<?php

namespace Tests\Feature;

use App\Models\GiftPage;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two bands of chips at the top of the designs page.
 *
 * Occasions and categories together filled a telephone screen before a
 * single box was visible — three lines of one and two of the other. Each
 * band is folded now to a single line that slides sideways under a finger,
 * with a button that opens the whole set.
 *
 * What is worth guarding here is not the folding, which is one class put on
 * by a script, but the two things folding could quietly cost.
 *
 * Every chip has to still be in the page. The occasion chips are the links
 * Google follows to the gift pages, and a band that dropped the ones it is
 * not showing would be a change to the site's own linking dressed up as a
 * change of layout.
 *
 * And the button has to say its words in the reader's language. In this
 * shop the Azerbaijani sentence is itself the translation key, so a new
 * one shows up untranslated rather than missing, which is easy to miss.
 */
class TheDesignsPageFoldsItsChipsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ProductCategory::create(['slug' => 'qutular', 'name' => 'Qutular', 'is_active' => true, 'sort_order' => 0]);
        Product::create(['name' => 'Qutu', 'slug' => 'qutu', 'category' => 'qutular',
            'is_active' => true, 'price' => 12, 'sort_order' => 0]);
    }

    public function test_each_band_has_a_button_that_names_it(): void
    {
        $page = $this->get('/dizaynlar');

        $page->assertOk();

        foreach (['occ-strip', 'cat-strip'] as $band) {
            $page->assertSee('id="'.$band.'"', false);
            $page->assertSee('data-strip="'.$band.'"', false);
            $page->assertSee('aria-controls="'.$band.'"', false);
        }
    }

    /** Folding is a class, not a deletion: every link is still in the page. */
    public function test_every_occasion_is_still_in_the_page(): void
    {
        $shown = GiftPage::shown()->inLocale('az')->get();

        $this->assertGreaterThan(5, $shown->count(),
            'too few gift pages for this test to be measuring anything');

        $body = $this->get('/dizaynlar')->content();

        $this->assertSame($shown->count(), substr_count($body, 'class="occ-chip"'),
            'the band carries one chip per gift page whether it is folded or not');

        foreach ($shown as $gift) {
            $this->assertStringContainsString($gift->url(), $body, $gift->slug.' lost its link');
        }
    }

    /** The same for the shelves, and «Hamısı» in front of them. */
    public function test_every_category_is_still_in_the_page(): void
    {
        ProductCategory::create(['slug' => 'posterler', 'name' => 'Posterlər', 'is_active' => true, 'sort_order' => 1]);
        Product::create(['name' => 'Poster', 'slug' => 'poster', 'category' => 'posterler',
            'is_active' => true, 'price' => 9, 'sort_order' => 0]);

        $body = $this->get('/dizaynlar')->content();

        $this->assertSame(3, substr_count($body, 'class="chip'),
            '«Hamısı» and one chip for each of the two shelves');
        $this->assertStringContainsString('Qutular (1)', $body);
        $this->assertStringContainsString('Posterlər (1)', $body);
        $this->assertStringContainsString('(2)', $body, '«Hamısı» counts both');
    }

    /** Open and shut, in the language of the page being read. */
    public function test_the_button_speaks_the_language_of_the_page(): void
    {
        $says = [
            '/dizaynlar' => ['Hamısını göstər', 'Yığ'],
            '/ru/dizaynlar' => ['Показать все', 'Свернуть'],
            '/en/dizaynlar' => ['Show all', 'Collapse'],
        ];

        foreach ($says as $url => [$open, $shut]) {
            $body = $this->get($url)->content();

            $this->assertStringContainsString('data-open="'.$open.'"', $body, $url);
            $this->assertStringContainsString('data-shut="'.$shut.'"', $body, $url);
        }
    }

    /**
     * Without the script the bands wrap as they always did.
     *
     * The folding class is added by JavaScript and the button starts
     * hidden, so a reader with no script — and a crawler — gets the old
     * page rather than one line of chips and no way to reach the rest.
     */
    public function test_the_page_as_it_arrives_is_not_folded(): void
    {
        $body = $this->get('/dizaynlar')->content();

        /* The class attributes as they are written, rather than the word
           anywhere in the page — the script that puts the class on names
           it too, and an assertion that cannot tell the two apart is not
           reading the markup at all. */
        $this->assertStringContainsString('class="occ-chips strip"', $body);
        $this->assertStringContainsString('class="filter-bar strip"', $body);
        $this->assertSame(2, substr_count($body, 'class="roll-more" hidden'),
            'both buttons wait for the script to say they are needed');
    }
}
