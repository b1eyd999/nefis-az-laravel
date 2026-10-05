<?php

namespace Tests\Feature;

use App\Http\Middleware\SignedInPagesAreNotKept;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Signing out of a page that has been open for a while.
 *
 * The sign-out in the menu is the form people leave open longest — a shop is
 * opened in the morning and closed at night — and after two hours its token is
 * no longer the session's. Laravel answers that with a bare English
 * "Page Expired", which is what the owner was shown instead of being signed
 * out.
 */
class SigningOutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The token check skips itself while the suite runs, so it is woken up on
     * purpose here: this whole test is about what that check does.
     */
    private function withTheTokenCheckOn(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_signing_out_after_the_session_ran_out_simply_takes_him_home(): void
    {
        $this->withTheTokenCheckOn();

        // No session, no token — exactly what a page left open overnight sends.
        $this->post('/logout')->assertRedirect(route('home'));
    }

    public function test_and_home_in_the_language_he_was_reading(): void
    {
        $this->withTheTokenCheckOn();

        $this->post('/ru/logout')->assertRedirect(route('ru.home'));
        $this->post('/en/logout')->assertRedirect(route('en.home'));
    }

    public function test_the_same_in_the_admin_panel(): void
    {
        $this->withTheTokenCheckOn();

        $this->post('/admin/logout')->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_a_bad_token_on_a_live_session_still_stops_but_says_so_in_azerbaijani(): void
    {
        $this->withTheTokenCheckOn();

        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertStatus(419)
            ->assertSee('Səhifənin vaxtı bitdi')
            ->assertDontSee('Page Expired');
    }

    public function test_that_page_carries_a_way_out_that_works(): void
    {
        $this->withTheTokenCheckOn();

        // Drawn now, so its own token is good: one tap finishes the sign-out.
        $this->actingAs(User::factory()->create())->post('/logout')->assertStatus(419)
            ->assertSee('name="_token"', false)
            ->assertSee('action="'.route('logout').'"', false);
    }

    public function test_any_other_expired_form_gets_the_same_page_rather_than_laravels(): void
    {
        $this->withTheTokenCheckOn();

        $this->post('/sirketler-ucun', ['name' => 'Test'])
            ->assertStatus(419)
            ->assertSee('Səhifənin vaxtı bitdi')
            ->assertSee('419');
    }

    public function test_signing_out_the_ordinary_way_is_untouched(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    /* ------------------------------------------------- and what is kept */

    public function test_a_page_drawn_for_someone_signed_in_tells_the_worker_not_to_keep_it(): void
    {
        $this->get('/')->assertOk()->assertHeaderMissing(SignedInPagesAreNotKept::HEADER);

        $this->actingAs(User::factory()->create())
            ->get('/')->assertOk()
            ->assertHeader(SignedInPagesAreNotKept::HEADER, '1');
    }

    /**
     * The one that was actually biting people: the sign-out in the phone menu
     * went out without a token, so on a phone — where that menu is the only
     * way to it — pressing Çıxış never signed anybody out. It answered
     * "419 Page Expired" every single time, session fresh or not.
     */
    public function test_the_sign_out_in_the_phone_menu_carries_its_token(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->content();

        preg_match_all('#<form[^>]*method="POST"[^>]*>(.*?)</form>#is', $html, $forms, PREG_SET_ORDER);
        $this->assertNotEmpty($forms);

        $signOuts = array_filter($forms, fn ($f) => str_contains($f[0], '/logout'));
        $this->assertCount(2, $signOuts, 'the account menu and the phone menu');

        foreach ($forms as $form) {
            $this->assertStringContainsString('name="_token"', $form[1],
                'a POST form without a token answers 419 instead of doing its job: '
                    .substr(preg_replace('/\s+/', ' ', $form[0]), 0, 120));
        }
    }

    public function test_and_pressing_it_really_signs_him_out(): void
    {
        $this->withTheTokenCheckOn();
        $user = User::factory()->create();

        // The token the phone menu would actually send.
        $html = $this->actingAs($user)->get('/')->content();
        preg_match('#<form[^>]*action="[^"]*logout[^"]*"[^>]*>\s*<input[^>]*name="_token" value="([^"]+)"#i', $html, $m);
        $this->assertNotEmpty($m, 'the form must carry a token to send');

        $this->actingAs($user)->post('/logout', ['_token' => $m[1]])->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_a_closed_shop_still_lets_people_in_and_out_in_every_language(): void
    {
        Setting::put(Setting::MAINTENANCE, '1');
        $user = User::factory()->create();

        // The shop itself is closed to a customer...
        $this->get('/ru')->assertStatus(503);

        // ...but the door is not locked from the inside.
        foreach (['/login', '/ru/login', '/en/login'] as $door) {
            $this->get($door)->assertOk();
        }
        $this->actingAs($user)->post('/ru/logout')->assertRedirect(route('ru.home'));
        $this->assertGuest();
    }

    public function test_the_worker_knows_this_shops_real_addresses(): void
    {
        $worker = file_get_contents(public_path('sw.js'));

        // The sign-in and password pages carry a token of their own.
        foreach (['/login', '/register', '/sifre-unutdum', '/sifre-yenile'] as $path) {
            $this->assertStringContainsString("'".$path."'", $worker, $path.' must never be kept');
        }

        $this->assertStringContainsString(SignedInPagesAreNotKept::HEADER, $worker,
            'and it must honour the header for everything else');
    }
}
