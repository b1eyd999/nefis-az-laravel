<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shop as an app on a phone.
 *
 * Not from the App Store and not a bookmark: the manifest is what makes a
 * phone treat the site as an application — its own icon, its own window, its
 * own opening screen — and the worker is what makes it open with no signal.
 */
class AppOnThePhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_phone_is_told_this_is_an_app_in_the_language_it_is_reading(): void
    {
        $az = $this->get('/manifest-az.webmanifest')->assertOk()->json();

        $this->assertSame('standalone', $az['display'], 'its own window, not a tab');
        $this->assertSame('/', $az['start_url']);
        $this->assertSame('az', $az['lang']);
        $this->assertSame('Nefis', $az['short_name']);
        $this->assertSame('#FBF4EA', $az['background_color']);

        $ru = $this->get('/manifest-ru.webmanifest')->assertOk()->json();
        $this->assertSame('/ru/', $ru['start_url'], 'the app opens in his own language');
        $this->assertSame('ru', $ru['lang']);
        $this->assertNotSame($az['name'], $ru['name'], 'and it is called what it is called there');

        $this->get('/manifest-de.webmanifest')->assertNotFound();
    }

    public function test_it_carries_the_icons_a_phone_needs_including_one_it_may_cut_into_a_shape(): void
    {
        $icons = $this->get('/manifest-az.webmanifest')->assertOk()->json('icons');

        $sizes = array_column($icons, 'sizes');
        $this->assertContains('192x192', $sizes);
        $this->assertContains('512x512', $sizes);

        $maskable = array_filter($icons, fn ($i) => ($i['purpose'] ?? null) === 'maskable');
        $this->assertNotEmpty($maskable, 'Android cuts the icon into its own shape');

        foreach ($icons as $icon) {
            $path = public_path(parse_url($icon['src'], PHP_URL_PATH));
            $this->assertFileExists($path, $icon['src'].' is in the manifest but not on disk');
        }
    }

    public function test_the_shop_links_the_manifest_and_says_how_to_open_in_its_own_window(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest-az.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('apple-touch-startup-image', false);

        $this->get('/ru')->assertOk()->assertSee('/manifest-ru.webmanifest', false);
    }

    public function test_every_opening_screen_it_promises_actually_exists(): void
    {
        $page = $this->get('/')->assertOk()->content();

        preg_match_all('#href="([^"]*images/splash/[^"?]+)#', $page, $found);
        $this->assertNotEmpty($found[1]);

        foreach (array_unique($found[1]) as $src) {
            $this->assertFileExists(public_path(parse_url($src, PHP_URL_PATH)));
        }
    }

    public function test_there_is_something_to_read_with_no_signal_in_each_language(): void
    {
        $this->get('/oflayn')->assertOk()->assertSee('İnternet yoxdur');
        $this->get('/ru/oflayn')->assertOk()->assertSee('Нет интернета');
        $this->get('/en/oflayn')->assertOk()->assertSee('No connection');
    }

    public function test_the_worker_leaves_alone_everything_that_is_not_the_shop(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // The screens that belong to the shop's own people, and the gateway.
        foreach (['/admin', '/admin-phone', '/kuryer', '/epoint', '/sohbet', '/livewire'] as $path) {
            $this->assertStringContainsString("'".$path."'", $worker, $path.' must never be cached');
        }

        // And what belongs to one customer rather than to everybody.
        foreach (['/cart', '/checkout', '/orders'] as $path) {
            $this->assertStringContainsString("'".$path."'", $worker);
        }

        // The three ways of saying "no signal" are the ones it keeps.
        foreach (['/oflayn', '/ru/oflayn', '/en/oflayn'] as $path) {
            $this->assertStringContainsString("'".$path."'", $worker);
        }
    }

    public function test_the_offer_to_install_is_there_but_says_nothing_until_it_is_asked_to(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="pwa-bar"', false)
            ->assertSee('hidden', false)
            ->assertSee('js/pwa.js', false);
    }
}
