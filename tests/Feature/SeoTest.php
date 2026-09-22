<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\GiftPageResource\Pages\CreateGiftPage;
use App\Models\GiftPage;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    /** A design a customer can open: a box built in the editor. */
    private function box(string $name, string $slug, float $price = 4.9, bool $active = true): Product
    {
        $box = Product::create(['name' => $name, 'slug' => $slug, 'is_active' => $active, 'price' => $price, 'category' => 'sokolad',
            'template_width' => 969, 'template_height' => 1895, 'preview_image' => 'boxes/' . $slug . '.webp']);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** @return array<int, array<string, mixed>> the JSON-LD blocks of a page */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return array_map(fn (string $j) => json_decode($j, true, flags: JSON_THROW_ON_ERROR), $m[1]);
    }

    public function test_the_gift_pages_are_there_from_the_start_and_linked_everywhere(): void
    {
        $this->assertSame(13, GiftPage::count());

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('Hər Münasibətə Fərdi Hədiyyə')
            ->assertSee('href="' . route('gifts.show', 'ad-gunu') . '"', false)
            ->assertSee('Ad günü hədiyyəsi')      // the footer and cards say what people search for
            ->assertSee('Sevgiliyə hədiyyə')
            ->assertDontSee('Sevgiliyə hədiyyəsi')
            ->getContent();

        $this->get(route('gifts.index'))->assertOk()
            ->assertSee('<h1>Hədiyyə fikirləri</h1>', false)
            ->assertSee('Gül əvəzinə hədiyyə')
            ->assertSee('8 Mart hədiyyəsi');

        $this->get(route('designs.index'))->assertOk()->assertSee('href="' . route('gifts.show', '8-mart') . '"', false);
        $this->assertStringContainsString('href="' . route('gifts.index') . '"', $html);
    }

    public function test_a_gift_page_has_its_words_designs_questions_and_structured_data(): void
    {
        $love = $this->box('Love Story', 'love-story-vol-1', 6.5);
        $kinder = $this->box('Kinder', 'kinder-vol-1');
        $page = GiftPage::where('slug', 'sevgiliye')->first();
        $page->products()->sync([$love->id]);

        $html = $this->get(route('gifts.show', 'sevgiliye'))->assertOk()
            ->assertSee('<title>Sevgiliyə hədiyyə — birgə şəkillə fərdi şokolad | Nefis</title>', false)
            ->assertSee('<h1>Sevgiliyə hədiyyə — şəkilli şokolad qutusu</h1>', false)
            ->assertSee('<link rel="canonical" href="' . route('gifts.show', 'sevgiliye') . '">', false)
            ->assertSee('<h2>Sevgiliyə nə almaq olar?</h2>', false)   // the article's Markdown
            ->assertSee('Qutu 6.50 ₼-dan')
            ->assertSee(route('products.customize', 'love-story-vol-1'))
            ->assertDontSee(route('products.customize', 'kinder-vol-1'))
            ->assertSee('Canlı şəkil nədir?')
            ->getContent();

        $types = collect($this->jsonLd($html))->flatMap(fn ($b) => $b['@graph'] ?? [$b])->pluck('@type');
        $this->assertEqualsCanonicalizing(['BreadcrumbList', 'FAQPage', 'ItemList'], $types->all());

        // Nothing picked: every design a customer can open.
        $this->get(route('gifts.show', 'ad-gunu'))->assertOk()
            ->assertSee(route('products.customize', 'love-story-vol-1'))
            ->assertSee(route('products.customize', 'kinder-vol-1'))
            ->assertSee('Qutu 4.90 ₼-dan');
    }

    public function test_a_hidden_page_is_gone_everywhere(): void
    {
        GiftPage::where('slug', 'yeni-il')->update(['is_active' => false]);

        $this->get(route('gifts.show', 'yeni-il'))->assertNotFound();
        $this->get('/hediyye/yoxdur')->assertNotFound();
        $this->get(route('gifts.index'))->assertDontSee('Yeni il hədiyyəsi');
        $this->get(route('sitemap'))->assertDontSee('/hediyye/yeni-il');
    }

    public function test_the_article_cannot_carry_html_and_the_data_cannot_break_out_of_its_script(): void
    {
        GiftPage::where('slug', 'dosta')->update([
            'body' => "## Salam\n\n<script>alert(1)</script>\n\nSöz **qalın** <b onclick=\"x()\">b</b>",
            'faq' => json_encode([['q' => 'Sual </script><script>alert(2)</script>', 'a' => 'Cavab']]),
        ]);

        $html = $this->get(route('gifts.show', 'dosta'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringContainsString('<strong>qalın</strong>', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function test_the_sitemap_lists_the_pages_and_designs_with_their_pictures(): void
    {
        $this->box('Love Story', 'love-story-vol-1');
        $this->box('Köhnə', 'kohne', active: false);

        $xml = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'the sitemap is valid XML');
        $locs = [];
        foreach ($doc->url as $u) {
            $locs[] = (string) $u->loc;
        }

        $this->assertContains(route('home'), $locs);
        $this->assertContains(route('designs.index'), $locs);
        $this->assertContains(route('gifts.show', 'ad-gunu'), $locs);
        $this->assertContains(route('products.customize', 'love-story-vol-1'), $locs);
        $this->assertNotContains(route('products.customize', 'kohne'), $locs);
        $this->assertStringContainsString('<image:loc>', $xml);

        // robots.txt is a plain file the web server hands out.
        $this->assertStringContainsString('Sitemap: https://nefis.az/sitemap.xml', file_get_contents(public_path('robots.txt')));
    }

    public function test_a_row_with_an_impossible_date_does_not_bring_the_sitemap_down(): void
    {
        $this->box('Love Story', 'love-story-vol-1');
        \DB::table('products')->update(['updated_at' => '0000-00-00 00:00:00', 'created_at' => null]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString(route('products.customize', 'love-story-vol-1'), $xml);
        $this->assertStringNotContainsString('0000', $xml);
    }

    public function test_every_page_names_itself_and_private_ones_stay_out_of_search(): void
    {
        $this->get(route('designs.index'))->assertOk()
            ->assertSee('<link rel="canonical" href="' . route('designs.index') . '">', false)
            ->assertSee('<meta property="og:url" content="' . route('designs.index') . '">', false)
            ->assertSee('og-nefis.jpg')
            ->assertDontSee('name="robots"', false);

        $home = $this->get(route('home'))->assertOk()
            ->assertSee('<title>Nefis — Şəkilli Şokolad Qutuları və Fərdi Hədiyyələr Bakıda</title>', false)
            ->getContent();
        $types = collect($this->jsonLd($home))->flatMap(fn ($b) => $b['@graph'] ?? [$b])->pluck('@type')->all();
        $this->assertEqualsCanonicalizing(['OnlineStore', 'WebSite', 'FAQPage'], $types);

        foreach ([route('cart.index'), route('login'), route('register')] as $url) {
            $this->get($url)->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
    }

    public function test_the_site_answers_on_www_but_never_names_itself_that_way(): void
    {
        $this->box('Love Story', 'love-story-vol-1');

        // The server sends www. visitors to the plain address; a page reached
        // that way must still point search engines at the one address.
        $this->get('http://www.nefis.az/dizaynlar')->assertOk()
            ->assertSee('<link rel="canonical" href="http://nefis.az/dizaynlar">', false)
            ->assertSee('<meta property="og:url" content="http://nefis.az/dizaynlar">', false);

        $this->get('http://www.nefis.az/sitemap.xml')->assertOk()->assertDontSee('www.nefis.az');

        $htaccess = file_get_contents(public_path('.htaccess'));
        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^www\.(.+)$ [NC]', $htaccess);
    }

    public function test_a_design_page_tells_search_engines_what_it_sells(): void
    {
        $this->box('Frame & Player', 'frame-player', 4.9);

        $html = $this->get(route('products.customize', 'frame-player'))->assertOk()
            ->assertSee('<title>Frame &amp; Player — şəkilli şokolad qutusu | Nefis</title>', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->getContent();

        $product = collect($this->jsonLd($html))->flatMap(fn ($b) => $b['@graph'] ?? [$b])->firstWhere('@type', 'Product');
        $this->assertSame('Frame & Player — şəkilli şokolad qutusu', $product['name']);
        $this->assertSame('4.90', $product['offers']['price']);
        $this->assertSame('AZN', $product['offers']['priceCurrency']);
        $this->assertStringContainsString('4.90 ₼-dan', $product['description']);
    }

    public function test_the_catalogue_cards_are_links_search_engines_can_follow(): void
    {
        $this->box('Love Story', 'love-story-vol-1');

        $this->get(route('designs.index'))->assertOk()
            ->assertSee('<a class="d-card" href="' . route('products.customize', 'love-story-vol-1') . '"', false);
    }

    public function test_the_owner_pastes_the_verification_codes_and_writes_a_new_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'seo_google' => '<meta name="google-site-verification" content="abcDEF123_-xyz" />',
                'seo_yandex' => '9f8e7d6c5b4a',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('abcDEF123_-xyz', Setting::get(Setting::SEO_GOOGLE));

        $this->get(route('home'))
            ->assertSee('<meta name="google-site-verification" content="abcDEF123_-xyz">', false)
            ->assertSee('<meta name="yandex-verification" content="9f8e7d6c5b4a">', false)
            ->assertDontSee('msvalidate.01', false);

        $box = $this->box('Müəllim', 'muellim');
        $this->get('/admin/gift-pages')->assertOk()->assertSee('Hədiyyə səhifələri');

        Livewire::test(CreateGiftPage::class)
            ->fillForm(['menu_label' => 'Müəllimə', 'title' => 'Müəllimə hədiyyə', 'slug' => 'Müəllimə hədiyyə'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);

        Livewire::test(CreateGiftPage::class)
            ->fillForm(['menu_label' => 'Müəllimə', 'emoji' => '📚', 'title' => 'Müəllimə hədiyyə', 'slug' => 'muellime',
                'intro' => 'Müəllimlər günü üçün.', 'body' => "## Nə vermək olar?\n\nŞəkilli qutu.",
                'faq' => [['q' => 'Nə vaxt?', 'a' => 'Oktyabrda.']], 'products' => [$box->id], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get(route('gifts.show', 'muellime'))->assertOk()
            ->assertSee('<title>Müəllimə hədiyyə | Nefis</title>', false)
            ->assertSee('Müəllimlər günü üçün.')
            ->assertSee(route('products.customize', 'muellim'));
        $this->get(route('home'))->assertSee('Müəllimə hədiyyə');
    }
}
