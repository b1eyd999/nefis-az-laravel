<?php

namespace Tests\Feature;

use App\Models\GiftPage;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One page, one address.
 *
 * A slug matches whatever its case, so every gift page and every design had an
 * endless family of addresses that each called itself the canonical one; and
 * the language menu built the other languages' addresses out of the current
 * page's own word, which for a gift page is a different word in each language.
 */
class OneAddressPerPageTest extends TestCase
{
    use RefreshDatabase;

    private function box(string $slug = 'love-story-vol-1'): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => $slug, 'is_active' => true, 'price' => 6.5,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_a_slug_in_the_wrong_case_is_sent_to_the_real_address(): void
    {
        $this->box();

        // MySQL, which the shop runs on, matches a slug whatever its case, so
        // /hediyye/AD-GUNU answers as well as the real address and calls itself
        // canonical. SQLite does not, so here the guard can only be read.
        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() !== 'mysql') {
            $this->assertStringContainsString(
                "originalParameter('product')",
                file_get_contents(app_path('Http/Controllers/ProductController.php')),
            );
            $this->assertStringContainsString(
                "originalParameter('giftPage')",
                file_get_contents(app_path('Http/Controllers/GiftPageController.php')),
            );

            $this->get(route('gifts.show', 'sevgiliye'))->assertOk();
            $this->get(route('products.customize', 'love-story-vol-1'))->assertOk();

            return;
        }

        $this->get('/products/LOVE-Story-Vol-1/customize')
            ->assertRedirect(route('products.customize', 'love-story-vol-1'))
            ->assertStatus(301);

        $this->get('/hediyye/SEVGILIYE')
            ->assertRedirect(route('gifts.show', 'sevgiliye'))
            ->assertStatus(301);

        // The right address still answers, and does not bounce.
        $this->get(route('gifts.show', 'sevgiliye'))->assertOk();
    }

    public function test_the_language_menu_on_a_gift_page_lands_on_its_twin(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');

        $az = GiftPage::inLocale('az')->where('slug', 'sevgiliye')->firstOrFail();
        $ru = GiftPage::inLocale('ru')->where('alt_of', $az->id)->where('is_active', true)->first();
        $this->assertNotNull($ru, 'the Russian twin ships with the site');

        $html = $this->get($az->url())->assertOk()->getContent();

        $this->assertStringContainsString('href="' . $ru->url() . '"', $html);
        // Never the Azerbaijani word under a Russian prefix: that address is a 404.
        $this->assertStringNotContainsString('/ru/podarki/' . $az->slug, $html);
        $this->get('/ru/podarki/' . $az->slug)->assertNotFound();
    }

    public function test_a_tracking_tag_does_not_reach_the_other_languages(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');

        $html = $this->get(route('designs.index') . '?utm_source=instagram')->assertOk()->getContent();

        preg_match_all('/<link rel="alternate" hreflang="[^"]+" href="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $href) {
            $this->assertStringNotContainsString('utm_', $href, 'an alternate that is not canonical is ignored');
        }
    }

    public function test_a_wrong_address_is_still_the_shop(): void
    {
        $this->get('/bele-sehife-yoxdur')
            ->assertNotFound()
            ->assertSee('<html lang="az">', false)
            ->assertSee(__('Belə bir səhifə yoxdur'), false)
            ->assertSee('noindex, follow', false)
            ->assertSee(route('designs.index'), false);

        // And it speaks the language of the address it was reached at.
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
        $this->get('/ru/net-takoy-stranicy')->assertNotFound()->assertSee('<html lang="ru">', false);
    }
}
