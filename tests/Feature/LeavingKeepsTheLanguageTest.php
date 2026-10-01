<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which language a page comes back in is decided by its address — including
 * the pages that have no address of their own, like signing out.
 */
class LeavingKeepsTheLanguageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
    }

    public function test_signing_out_of_the_russian_shop_lands_on_the_russian_shop(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('ru.logout'))->assertRedirect(route('ru.home'));
        $this->assertGuest();

        $this->actingAs($customer)->post(route('en.logout'))->assertRedirect(route('en.home'));
        $this->actingAs($customer)->post(route('logout'))->assertRedirect(route('home'));
    }

    public function test_a_page_without_a_language_in_its_address_is_azerbaijani(): void
    {
        // The server's own APP_LOCALE must not decide this: it used to, and
        // signing out came back in English.
        config(['app.locale' => 'en']);
        app()->setLocale('en');

        $this->get('/')->assertOk()->assertSee('lang="az"', false);
        $this->get('/qaydalar')->assertOk()->assertSee('lang="az"', false)->assertSee('İstifadə şərtləri');
    }

    public function test_the_russian_pages_are_still_russian(): void
    {
        $this->get('/ru')->assertOk()->assertSee('lang="ru"', false);
        $this->get('/en')->assertOk()->assertSee('lang="en"', false);
        $this->get('/')->assertOk()->assertSee('lang="az"', false);
    }
}
