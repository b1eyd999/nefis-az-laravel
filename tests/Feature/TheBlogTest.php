<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The blog: /bloq, /ru/blog, /en/blog.
 *
 * Three things decide whether it works, and all three are invisible in the
 * admin until they are wrong. `published_at` is what shows an article and
 * what holds one back; `alt_of` is what ties a translation to its original,
 * and hreflang is built from nothing else; and the addresses written inside
 * the articles themselves are plain text that nothing else checks.
 */
class TheBlogTest extends TestCase
{
    use RefreshDatabase;

    private function article(array $extra = []): Post
    {
        return Post::create($extra + [
            'slug' => 'test-yazi',
            'locale' => 'az',
            'topic' => 'Sınaq',
            'emoji' => '🧪',
            'title' => 'Sınaq yazısı',
            'excerpt' => 'Bir neçə söz.',
            'body' => "## Başlıq\n\nBir az mətn.",
            'published_at' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- shown

    public function test_the_blog_lists_the_articles_of_the_language_being_read(): void
    {
        $page = $this->get('/bloq');

        $page->assertOk();
        $page->assertSee('Qutuya hansı şəkli seçmək lazımdır');
        // The Russian twin of the same article belongs to /ru/blog alone.
        $page->assertDontSee('Какое фото выбрать для коробки');

        $this->get('/ru/blog')->assertOk()->assertSee('Какое фото выбрать для коробки');
        $this->get('/en/blog')->assertOk()->assertSee('Which photo to put on the box');
    }

    /**
     * A date in the future is how an article is written today and appears on
     * Monday. Until then it is not on the hub and its own address is a 404 —
     * otherwise "not published yet" would only mean "not linked to".
     */
    public function test_an_article_dated_tomorrow_is_not_on_the_site_yet(): void
    {
        $this->article(['slug' => 'gelecek', 'title' => 'Gələcək yazı', 'published_at' => now()->addDay()]);

        $this->get('/bloq')->assertDontSee('Gələcək yazı');
        $this->get('/bloq/gelecek')->assertNotFound();

        $this->travel(2)->days();

        $this->get('/bloq')->assertSee('Gələcək yazı');
        $this->get('/bloq/gelecek')->assertOk();
    }

    public function test_an_article_with_no_date_at_all_is_never_shown(): void
    {
        $this->article(['slug' => 'tarixsiz', 'title' => 'Tarixsiz yazı', 'published_at' => null]);

        $this->get('/bloq')->assertDontSee('Tarixsiz yazı');
        $this->get('/bloq/tarixsiz')->assertNotFound();
    }

    public function test_an_article_switched_off_is_not_shown(): void
    {
        $this->article(['slug' => 'gizli', 'title' => 'Gizli yazı', 'is_active' => false]);

        $this->get('/bloq')->assertDontSee('Gizli yazı');
        $this->get('/bloq/gizli')->assertNotFound();
    }

    public function test_the_newest_article_stands_first(): void
    {
        $slugs = Post::shown()->inLocale('az')->pluck('slug')->all();

        $this->assertSame('qutuya-hansi-sekli-secmek', $slugs[0]);
        $this->assertSame('uzaqdan-hediyye-gondermek', end($slugs));
    }

    // ------------------------------------------------------- across tongues

    /**
     * An article's slug is a word in its own language, but the route finds a
     * row by slug whatever prefix the address carries. It names a page that
     * exists, so the answer is where that page lives.
     */
    public function test_a_slug_asked_for_in_the_wrong_language_is_sent_to_the_twin(): void
    {
        $this->get('/ru/blog/qutuya-ne-yazmaq')
            ->assertRedirect(route('ru.blog.show', 'chto-napisat-na-korobke'));

        $this->get('/en/blog/chto-napisat-na-korobke')
            ->assertRedirect(route('en.blog.show', 'what-to-write-on-the-box'));
    }

    /** With no twin written, the reader lands among the articles, not on the home page. */
    public function test_with_no_twin_the_reader_lands_on_that_languages_blog(): void
    {
        $this->article(['slug' => 'yalniz-azerbaycanca']);

        $this->get('/ru/blog/yalniz-azerbaycanca')->assertRedirect(route('ru.blog.index'));
    }

    public function test_hreflang_names_one_address_per_language_and_no_more(): void
    {
        $html = $this->get('/ru/blog/chto-napisat-na-korobke')->assertOk()->getContent();

        preg_match_all('/<link rel="alternate" hreflang="([a-z-]+)"/', $html, $found);

        $this->assertSame(['ru', 'az', 'en', 'x-default'], $found[1],
            'a family that names a language twice is unusable to a search engine');
    }

    /** A twin switched off is not announced as if it were live. */
    public function test_a_hidden_twin_is_left_out_of_the_family(): void
    {
        Post::where('slug', 'what-to-write-on-the-box')->update(['is_active' => false]);

        $html = $this->get('/ru/blog/chto-napisat-na-korobke')->assertOk()->getContent();

        /* The <link> lines, not every hreflang on the page: the language
           switcher in the header carries one on each of its three links, so
           a bare search for hreflang="en" finds it there and passes whatever
           the family says. */
        $this->assertStringNotContainsString('<link rel="alternate" hreflang="en"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="az"', $html);
    }

    /** Every seeded translation hangs off the Azerbaijani article. */
    public function test_every_translation_knows_its_original(): void
    {
        $orphans = Post::where('locale', '!=', 'az')->whereNull('alt_of')->pluck('slug');

        $this->assertSame([], $orphans->all(),
            'without alt_of these ship as strangers and hreflang never names them');

        foreach (Post::where('locale', '!=', 'az')->get() as $post) {
            $this->assertSame('az', Post::find($post->alt_of)?->locale,
                $post->slug . ' must hang off the Azerbaijani article');
        }
    }

    public function test_the_date_is_written_in_the_articles_own_language(): void
    {
        $this->assertSame('30 oktyabr 2026', Post::where('slug', 'qutuya-hansi-sekli-secmek')->first()->dateText());
        $this->assertSame('30 октября 2026', Post::where('slug', 'kakoe-foto-vybrat')->first()->dateText());
        $this->assertSame('October 30, 2026', Post::where('slug', 'which-photo-to-choose')->first()->dateText());
    }

    /**
     * The reading time counts words, not characters.
     *
     * str_word_count() would have been the obvious call and is wrong here:
     * it stops at the first Azerbaijani or Cyrillic letter, so every article
     * on this site would have claimed a reading time it does not have.
     */
    public function test_the_reading_time_counts_the_words_of_any_alphabet(): void
    {
        $this->assertSame(1, $this->article(['slug' => 'qisa', 'body' => 'Bir neçə söz.'])->readingMinutes());

        $long = $this->article(['slug' => 'uzun', 'body' => str_repeat('söz ', 700)]);
        $this->assertSame(5, $long->readingMinutes(), '700 words at 170 a minute');

        $cyrillic = $this->article(['slug' => 'kiril', 'locale' => 'ru', 'body' => str_repeat('слово ', 340)]);
        $this->assertSame(2, $cyrillic->readingMinutes());
    }

    // ------------------------------------------------------------- designs

    public function test_an_article_shows_the_designs_it_names(): void
    {
        $box = $this->box();
        $this->article(['slug' => 'dizayn-adlanan'])->products()->attach($box);

        $this->get('/bloq/dizayn-adlanan')
            ->assertOk()
            ->assertSee('Bu yazıda danışdığımız dizaynlar')
            ->assertSee('Sınaq qutusu');
    }

    /** An article that names none shows no design block, rather than the whole catalogue. */
    public function test_an_article_that_names_no_design_shows_no_design_block(): void
    {
        $this->box();
        $this->article(['slug' => 'dizaynsiz']);

        $this->get('/bloq/dizaynsiz')->assertOk()->assertDontSee('Bu yazıda danışdığımız dizaynlar');
    }

    public function test_a_translation_borrows_its_originals_designs(): void
    {
        $box = $this->box();
        $az = $this->article(['slug' => 'dizaynli']);
        $az->products()->attach($box);
        $ru = $this->article(['slug' => 'dizaynli-ru', 'locale' => 'ru', 'alt_of' => $az->id]);

        $this->assertSame([$box->id], $ru->shownProducts()->pluck('id')->all());
    }

    // -------------------------------------------------------- menu, sitemap

    public function test_the_blog_stands_in_the_menu_while_there_is_something_to_read(): void
    {
        $this->assertTrue(Menu::ready('blog'));
        $this->assertContains('blog', array_column(Menu::shown(), 'key'));
        $this->get('/bloq')->assertOk()->assertSee('Bloq');

        Post::query()->delete();

        $this->assertFalse(Menu::ready('blog'));
        $this->assertSame('Göstəriləcək bloq yazısı yoxdur.', Menu::why('blog'));
    }

    /**
     * The blog line stands inside «Məhsullar», not on the bar beside it.
     *
     * Not a matter of taste. The bar appears from 980px and at that width
     * the Azerbaijani words already run to 965 of them; «Bloq» costs about
     * sixty more, and the sign-up button goes off the right-hand edge. The
     * owner can still promote it in admin → Menyu, and will see at once
     * what it costs. The same measurement is why English and Russian have
     * their own, later widths.
     */
    public function test_the_blog_line_stands_in_the_list_rather_than_on_the_bar(): void
    {
        $this->assertSame('drop', Menu::settings()['blog']['place']);
        $this->assertSame([], array_filter(Menu::shownIn('top'), fn ($i) => $i['key'] === 'blog'));
    }

    public function test_the_sitemap_carries_the_blog_in_every_language(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        foreach (['/bloq', '/ru/blog', '/en/blog'] as $hub) {
            $this->assertStringContainsString('<loc>' . config('app.url') . $hub . '</loc>', $xml);
        }

        $this->assertStringContainsString('/bloq/qutuya-hansi-sekli-secmek', $xml);
        $this->assertStringContainsString('/ru/blog/kakoe-foto-vybrat', $xml);
    }

    /** An article held back for Monday has no place in the index either. */
    public function test_the_sitemap_leaves_out_an_article_not_published_yet(): void
    {
        $this->article(['slug' => 'gelecek-sitemap', 'published_at' => now()->addWeek()]);

        $this->assertStringNotContainsString('gelecek-sitemap', $this->get('/sitemap.xml')->getContent());
    }

    public function test_the_page_tells_google_it_is_an_article(): void
    {
        $html = $this->get('/bloq/canli-sekil-nedir')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BlogPosting"', $html);
        $this->assertStringContainsString('"datePublished":"2026-10-18', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
    }

    // ------------------------------------------------------- what is written

    /**
     * Every address written inside the articles leads somewhere.
     *
     * The links in the copy are plain text in a Markdown file; nothing else
     * on the site would notice a wrong one, and a dead link inside an
     * article is the one mistake a reader meets head-on.
     */
    public function test_every_link_written_inside_an_article_leads_somewhere(): void
    {
        $seen = 0;

        foreach (Post::all() as $post) {
            preg_match_all('#\]\((/[^)\s]*)\)#', (string) $post->body, $links);

            foreach (array_unique($links[1]) as $href) {
                $answer = $this->get($href);

                $this->assertContains($answer->getStatusCode(), [200, 301],
                    $post->slug . ' links to ' . $href . ', which answers ' . $answer->getStatusCode());
                $seen++;
            }
        }

        $this->assertGreaterThan(10, $seen, 'the articles should be linking to the shop at all');
    }

    /** A Russian article links into the Russian site, not the Azerbaijani one. */
    public function test_a_translation_links_inside_its_own_language(): void
    {
        foreach (Post::where('locale', '!=', 'az')->get() as $post) {
            preg_match_all('#\]\((/[^)\s]*)\)#', (string) $post->body, $links);

            foreach ($links[1] as $href) {
                $this->assertStringStartsWith('/' . $post->locale . '/', $href,
                    $post->slug . ' sends the reader out of ' . $post->locale . ' with ' . $href);
            }
        }
    }

    // ----------------------------------------------------------- the admin

    public function test_the_owner_edits_the_blog_in_the_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/admin/blog')->assertOk()->assertSee('Qutuya hansı şəkli seçmək lazımdır');
        $this->get('/admin/blog/create')->assertOk();
    }

    public function test_a_manager_does_not_get_into_the_blog(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'role' => User::MANAGER]));

        $this->get('/admin/blog')->assertForbidden();
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Sınaq qutusu', 'slug' => 'sinaq-qutusu', 'is_active' => true,
            'price' => 10, 'template_width' => 3508, 'template_height' => 2480]);
        $box->photoSlots()->create(['label' => 'Şəkil', 'x' => 700, 'y' => 700, 'width' => 400, 'height' => 400,
            'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false, 'sort_order' => 0]);

        return $box;
    }
}
