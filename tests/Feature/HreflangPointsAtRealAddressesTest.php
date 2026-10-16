<?php

namespace Tests\Feature;

use App\Models\GiftPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the three hreflang links at the top of every page actually point at.
 *
 * They are how Google is told that /qaydalar, /ru/qaydalar and /en/qaydalar
 * are one page in three languages rather than three near-identical pages.
 * The annotation only works if each target is the address that page
 * canonicalises to — point it anywhere else and Google is being told two
 * contradictory things about the same URL, which is how nine of them ended
 * up in Search Console under «page variant with a canonical tag».
 */
class HreflangPointsAtRealAddressesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The hreflang links the layout prints, whatever the status.
     *
     * Not asserted ok on purpose: a design with nothing drawn on it yet
     * answers 404 with a «not ready» page, and that page carries the same
     * layout and the same hreflang links — which is the thing under test.
     *
     * @return array<string, string> hreflang => href
     */
    private function alternates(string $url): array
    {
        $html = $this->get($url)->getContent();

        preg_match_all(
            '~<link rel="alternate" hreflang="([^"]+)" href="([^"]+)"~',
            (string) $html,
            $found,
            PREG_SET_ORDER
        );

        return collect($found)->mapWithKeys(fn ($m) => [$m[1] => html_entity_decode($m[2])])->all();
    }

    private function canonical(string $url): string
    {
        preg_match(
            '~<link rel="canonical" href="([^"]+)"~',
            (string) $this->get($url)->getContent(),
            $m
        );

        return html_entity_decode($m[1] ?? '');
    }

    public static function legalPages(): array
    {
        return [
            'terms' => ['/qaydalar'],
            'privacy' => ['/mexfilik'],
            'refund' => ['/odenis-ve-qaytarma'],
        ];
    }

    /**
     * @dataProvider legalPages
     */
    public function test_a_page_registered_as_a_plain_view_does_not_leak_the_frameworks_own_parameters(string $path): void
    {
        /* Route::view() keeps the view's name and the status code among the
           route's parameters. They are not part of any address, and they
           were being appended to all four hreflang links as a query string. */
        foreach (['', '/ru', '/en'] as $prefix) {
            foreach ($this->alternates($prefix . $path) as $lang => $href) {
                $this->assertStringNotContainsString('?', $href, "hreflang=$lang on $prefix$path has a query string");
                $this->assertStringNotContainsString('view=', $href);
                $this->assertStringNotContainsString('status=', $href);
            }
        }
    }

    /**
     * @dataProvider legalPages
     */
    public function test_each_hreflang_points_at_the_address_that_page_canonicalises_to(string $path): void
    {
        $links = $this->alternates($path);

        $this->assertSame(url($path), $links['az'] ?? null);
        $this->assertSame(url('/ru' . $path), $links['ru'] ?? null);
        $this->assertSame(url('/en' . $path), $links['en'] ?? null);
        $this->assertSame(url($path), $links['x-default'] ?? null);

        // And each of those addresses really does canonicalise to itself.
        foreach (['', '/ru', '/en'] as $prefix) {
            $this->assertSame(url($prefix . $path), $this->canonical($prefix . $path));
        }
    }

    public function test_a_page_with_a_real_parameter_keeps_it(): void
    {
        // The guard drops parameters that are not segments of the address;
        // it must not drop the ones that are.
        $product = Product::create([
            'name' => 'Qutu', 'slug' => 'bir-qutu', 'is_active' => true, 'price' => 10,
        ]);

        $links = $this->alternates('/products/' . $product->slug . '/customize');

        $this->assertSame(url('/products/bir-qutu/customize'), $links['az'] ?? null);
        $this->assertSame(url('/ru/products/bir-qutu/customize'), $links['ru'] ?? null);
        $this->assertSame(url('/en/products/bir-qutu/customize'), $links['en'] ?? null);
    }

    public function test_a_gift_page_still_points_at_its_translated_address(): void
    {
        // The gift pages take a different path through the same method: the
        // slug is a word in its own language, so the model supplies the URL.
        GiftPage::create([
            'locale' => 'az', 'slug' => 'zz-test-az', 'title' => 'Test',
            'menu_label' => 'Test', 'is_active' => true, 'sort_order' => 900,
        ]);

        foreach ($this->alternates('/hediyye/zz-test-az') as $lang => $href) {
            $this->assertStringNotContainsString('?', $href, "hreflang=$lang leaks a query string");
        }
    }

    public function test_the_catalogue_and_the_home_page_are_unaffected(): void
    {
        foreach (['/', '/dizaynlar', '/elaqe', '/sirketler-ucun'] as $path) {
            foreach ($this->alternates($path) as $lang => $href) {
                $this->assertStringNotContainsString('?', $href, "hreflang=$lang on $path has a query string");
            }
        }
    }
}
