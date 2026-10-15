<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\CookieNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The strip that asks whether Google Analytics may run.
 *
 * The thing worth guarding is that a refusal means something: until the
 * visitor says yes, nothing of Google's may be fetched. A banner that asks
 * politely while the counter already runs is worse than no banner at all.
 */
class CookieNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_of_googles_is_fetched_before_a_yes(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');

        $this->get(route('home'))->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertSee('cookie-bar', false);
    }

    public function test_it_asks_only_when_there_is_something_to_decide(): void
    {
        // Nothing optional running: the session and the form token are what
        // make the shop work, so there is nothing to ask about.
        Setting::put(Setting::SEO_ANALYTICS, '');
        $this->assertFalse(CookieNotice::enabled());
        $this->get(route('home'))->assertOk()->assertDontSee('cookie-bar', false);

        // A counter is configured: now the question is real.
        Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');
        $this->assertTrue(CookieNotice::enabled());
        $this->get(route('home'))->assertOk()->assertSee('cookie-bar', false);
    }

    public function test_the_owner_can_switch_the_strip_off(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');
        Setting::put(Setting::COOKIE_NOTICE, false);

        $this->assertFalse(CookieNotice::enabled());
        $this->get(route('home'))->assertOk()->assertDontSee('cookie-bar', false);
    }

    public function test_the_strip_offers_both_answers_and_a_way_to_read_more(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');

        $this->get(route('home'))->assertOk()
            ->assertSee('data-cookie="all"', false)
            ->assertSee('data-cookie="essential"', false)
            ->assertSee(route('legal.privacy'), false);
    }

    public function test_the_privacy_page_says_which_cookies_are_asked_about(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, 'G-PCXS029TE7');

        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Çərəzlər')
            ->assertSee('Google Analytics')
            // and a way to change one's mind
            ->assertSee('data-cookie-reset', false);
    }
}
