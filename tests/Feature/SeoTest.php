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
        $this->assertSame(19, GiftPage::inLocale('az')->count());
        $this->assertSame(19, GiftPage::inLocale('ru')->count());
        // every Azerbaijani page has its Russian twin and the other way round
        $this->assertSame(0, GiftPage::inLocale('ru')->whereNull('alt_of')->count());
        $this->assertSame(19, GiftPage::inLocale('az')->whereHas('alternates')->count());

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
            ->assertSee('<title>Sevgiliyə hədiyyə, birgə şəkillə fərdi şokolad | Nefis</title>', false)
            ->assertSee('<h1>Sevgiliyə hədiyyə, şəkilli şokolad qutusu</h1>', false)
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

    public function test_the_russian_pages_speak_russian_and_are_paired_with_their_twins(): void
    {
        // All three languages are offered to customers here.
        \App\Models\Setting::put(\App\Models\Setting::SITE_LANGUAGES, 'az,ru,en');

        $love = $this->box('Love Story', 'love-story-vol-1', 6.5);
        $this->box('Kinder', 'kinder-vol-1');
        GiftPage::inLocale('az')->where('slug', 'sevgiliye')->first()->products()->sync([$love->id]);

        $ru = route('ru.gifts.show', 'devushke');
        $az = route('gifts.show', 'sevgiliye');

        $html = $this->get($ru)->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee('<h1>Подарок девушке, шоколад с вашим фото</h1>', false)
            ->assertSee('<link rel="canonical" href="' . $ru . '">', false)
            ->assertSee('<h2>Что подарить девушке?</h2>', false)
            ->assertSee('Подходящие дизайны')
            ->assertSee('Коробка от 6.50 ₼')   // 6.50 is the design's price below
            ->assertSee('Доставка по Баку и регионам')
            // the designs come from the Azerbaijani twin, so they are picked in one
            // place; the links stay in the language the page is read in
            ->assertSee(route('ru.products.customize', 'love-story-vol-1'))
            ->assertDontSee(route('ru.products.customize', 'kinder-vol-1'))
            ->getContent();

        $this->assertStringContainsString('<link rel="alternate" hreflang="az" href="' . $az . '">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="ru" href="' . $ru . '">', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default" href="' . $az . '">', $html);

        // …and the Azerbaijani page points back at the Russian one.
        $this->get($az)->assertOk()
            ->assertSee('<html lang="az">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="' . $ru . '">', false)
            ->assertSee('href="' . $ru . '"', false);

        $this->get(route('ru.gifts.index'))->assertOk()
            ->assertSee('<h1>Идеи подарков</h1>', false)
            ->assertSee('Подарок на день рождения')
            ->assertSee('href="' . route('ru.gifts.show', 'vmesto-cvetov') . '"', false);

        // Each address belongs to one language only. (An old /podarki address
        // is sent on first, and only then found to be the wrong language.)
        $this->get('/ru/podarki/ad-gunu')->assertNotFound();
        $this->get('/hediyye/devushke')->assertNotFound();

        // The Azerbaijani pages stay Azerbaijani; the switcher offers the others.
        $this->get(route('home'))->assertOk()
            ->assertSee('href="' . route('ru.home') . '"', false)
            ->assertSee('href="' . route('en.home') . '"', false)
            ->assertDontSee('Подарок на день рождения');

        // The addresses the Russian pages were found at before still work.
        $this->get('/podarki')->assertRedirect('/ru/podarki');
        $this->get('/podarki/devushke')->assertRedirect('/ru/podarki/devushke');

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(route('ru.gifts.show', 'na-den-rozhdeniya'))
            ->assertSee(route('ru.gifts.index'));
    }

    public function test_a_gift_page_names_its_twins_in_all_three_languages(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');

        $az = route('gifts.show', 'ad-gunu');
        $ru = route('ru.gifts.show', 'na-den-rozhdeniya');
        $en = route('en.gifts.show', 'birthday-gift');

        $this->get($en)->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Birthday gift')
            ->assertSee('Gift ideas')                             // the page's own furniture
            ->assertSee('<link rel="alternate" hreflang="az" href="' . $az . '">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="' . $ru . '">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="' . $az . '">', false);

        // …and the Azerbaijani twin points at both of the others.
        $this->get($az)->assertOk()
            ->assertSee('<link rel="alternate" hreflang="en" href="' . $en . '">', false)
            ->assertSee('<link rel="alternate" hreflang="ru" href="' . $ru . '">', false);

        // The English hub lists them, and the address belongs to one language.
        $this->get(route('en.gifts.index'))->assertOk()->assertSee('Birthday gift');
        $this->get('/en/gifts/ad-gunu')->assertNotFound();
    }

    public function test_a_language_still_being_written_is_kept_out_of_search(): void
    {
        // Only Azerbaijani is offered: the other two are reachable, but quietly.
        Setting::put(Setting::SITE_LANGUAGES, 'az');

        $this->get('/ru')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertDontSee('hreflang="ru"', false);

        $this->get('/')->assertOk()
            ->assertDontSee('hreflang="ru"', false)
            ->assertDontSee('<meta name="robots" content="noindex', false);

        // The owner switches Russian on, and it joins the site properly.
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru');

        $this->get('/ru')->assertOk()
            ->assertDontSee('noindex', false)
            ->assertSee('hreflang="ru"', false);
        $this->get('/')->assertOk()->assertSee('hreflang="ru"', false)->assertDontSee('hreflang="en"', false);
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
            ->assertSee('<title>Nefis, Şəkilli Şokolad Qutuları və Fərdi Hədiyyələr Bakıda</title>', false)
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
            ->assertSee('<title>Frame &amp; Player, şəkilli şokolad qutusu | Nefis</title>', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->getContent();

        $product = collect($this->jsonLd($html))->flatMap(fn ($b) => $b['@graph'] ?? [$b])->firstWhere('@type', 'Product');
        $this->assertSame('Frame & Player, şəkilli şokolad qutusu', $product['name']);
        $this->assertSame('4.90', $product['offers']['price']);
        $this->assertSame('AZN', $product['offers']['priceCurrency']);
        $this->assertStringContainsString('4.90 ₼-dan', $product['description']);

        // Google asks an offer how it ships and whether it comes back; without
        // these two it writes to the owner about "missing fields".
        $shipping = $product['offers']['shippingDetails'];
        $this->assertSame('OfferShippingDetails', $shipping['@type']);
        $this->assertSame('AZN', $shipping['shippingRate']['currency']);
        $this->assertSame('AZ', $shipping['shippingDestination']['addressCountry']);
        $this->assertSame('DAY', $shipping['deliveryTime']['handlingTime']['unitCode']);
        $this->assertSame(
            'https://schema.org/MerchantReturnNotPermitted',
            $product['offers']['hasMerchantReturnPolicy']['returnPolicyCategory'],
        );
    }

    public function test_a_design_with_its_own_words_uses_them_everywhere(): void
    {
        $box = $this->box('Milka', 'milka');
        $box->update(['description' => 'Milka üslubunda bənövşəyi dizayn, Alp dağları fonunda birgə şəkliniz.']);

        $this->get(route('products.customize', 'milka'))->assertOk()
            ->assertSee('<meta name="description" content="Milka üslubunda bənövşəyi dizayn, Alp dağları fonunda birgə şəkliniz. Şəklinizi və sözlərinizi əlavə edin, Bakıda çatdırılma.">', false);

        // …and on the card, instead of the category name it used to repeat
        $this->get(route('home'))->assertOk()->assertSee('Milka üslubunda bənövşəyi dizayn');
    }

    public function test_the_visitor_counter_runs_when_the_owner_has_set_one(): void
    {
        // The deploy switched it on with the owner's own measurement id.
        $this->assertSame('G-PCXS029TE7', Setting::get(Setting::SEO_ANALYTICS));

        $this->get(route('home'))->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-PCXS029TE7', false)
            ->assertSee("gtag('config', 'G-PCXS029TE7');", false);

        // Pasting the whole snippet, or a stray one, leaves only the id.
        $this->assertSame('G-ABC1234567', \App\Support\Seo::measurementId('<script async src="https://www.googletagmanager.com/gtag/js?id=G-ABC1234567"></script>'));
        $this->assertSame('', \App\Support\Seo::measurementId('</script><script>alert(1)</script>'));

        Setting::put(Setting::SEO_ANALYTICS, '');
        $this->get(route('home'))->assertOk()->assertDontSee('googletagmanager', false);
    }

    public function test_the_shop_can_be_phoned_and_google_is_told_the_number(): void
    {
        $this->assertSame('+994992308050', Setting::get(Setting::CONTACT_PHONE));

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('href="tel:+994992308050"', false)
            ->assertSee('+994 99 230 80 50')                      // written the way people read it
            ->assertSee('href="https://wa.me/994992308050"', false)
            ->assertSee('Hər gün 10:00–20:00')
            ->getContent();

        $store = collect($this->jsonLd($html))->flatMap(fn ($b) => $b['@graph'] ?? [$b])->firstWhere('@type', 'OnlineStore');
        $this->assertSame('+994992308050', $store['telephone']);
        $this->assertSame('+994992308050', $store['contactPoint']['telephone']);

        // Cleared, the site simply stops offering a phone.
        Setting::put(Setting::CONTACT_PHONE, '');
        $this->get(route('home'))->assertOk()->assertDontSee('wa.me', false)->assertSee('Instagram');
    }

    public function test_a_design_page_points_at_its_occasions_and_its_neighbours(): void
    {
        $love = $this->box('Love Story', 'love-story-vol-1', 6.5);
        $kinder = $this->box('Kinder', 'kinder-vol-1');
        GiftPage::inLocale('az')->where('slug', 'sevgiliye')->first()->products()->sync([$love->id]);

        $this->get(route('products.customize', 'love-story-vol-1'))->assertOk()
            ->assertSee('Səhifənin yeri', false)                                  // the trail home
            ->assertSee('href="' . route('designs.index') . '"', false)
            ->assertSee('href="' . route('gifts.show', 'sevgiliye') . '"', false)  // where it is offered
            ->assertSee('Bunlara da baxın')
            ->assertSee(route('products.customize', 'kinder-vol-1'));              // its neighbour
    }

    public function test_the_designs_are_offered_to_google_shopping_as_a_feed(): void
    {
        $this->box('Love Story', 'love-story-vol-1', 6.5);
        $this->box('Köhnə', 'kohne', active: false);

        $xml = $this->get('/feed.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $this->assertNotFalse(simplexml_load_string($xml), 'the feed is valid XML');
        $this->assertStringContainsString('<g:price>6.50 AZN</g:price>', $xml);
        $this->assertStringContainsString(route('products.customize', 'love-story-vol-1'), $xml);
        $this->assertStringNotContainsString('kohne', $xml);
    }

    public function test_the_styles_are_a_file_of_their_own_so_pages_stay_small(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="[^"]*css/site\.css\?v=\d+">#', $html);
        $this->assertStringNotContainsString('--cocoa-soft:', $html, 'the shared styles no longer travel with every page');
        $this->assertLessThan(60_000, strlen($html), 'the page itself stays under 60 KB');
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
            ->fillForm(['menu_label' => 'Babaya', 'title' => 'Babaya hədiyyə', 'slug' => 'Babaya hədiyyə'])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'regex']);

        Livewire::test(CreateGiftPage::class)
            ->fillForm(['menu_label' => 'Babaya', 'emoji' => '🧓', 'title' => 'Babaya hədiyyə', 'slug' => 'babaya',
                'intro' => 'Baba üçün hədiyyə.', 'body' => "## Nə vermək olar?\n\nŞəkilli qutu.",
                'faq' => [['q' => 'Nə vaxt?', 'a' => 'Oktyabrda.']], 'products' => [$box->id], 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->get(route('gifts.show', 'babaya'))->assertOk()
            ->assertSee('<title>Babaya hədiyyə | Nefis</title>', false)
            ->assertSee('Baba üçün hədiyyə.')
            ->assertSee(route('products.customize', 'muellim'));
        $this->get(route('home'))->assertSee('Babaya hədiyyə');
    }
}
