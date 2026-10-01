<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The shop hears a customer arrive.
 *
 * A bot of its own, so the message does not land among the orders: who signed
 * up, their number, and a link straight to them in the admin. It is told after
 * the account exists and never in the customer's way — a bot that is down must
 * not stop somebody registering.
 */
class SignupNoticeTest extends TestCase
{
    use RefreshDatabase;

    private array $form = [
        'name' => 'Fidan',
        'email' => 'fidan@nefis.az',
        'phone' => '+994 50 123 45 67',
        'password' => 'sirr12345',
        'password_confirmation' => 'sirr12345',
    ];

    private function botIsSetUp(): void
    {
        Telegram::saveSignupToken('111:AAsignup');
        Setting::put(Setting::TELEGRAM_SIGNUP_CHAT, '-100500');
    }

    public function test_the_owner_is_told_who_has_just_signed_up(): void
    {
        $this->botIsSetUp();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->post(route('register'), $this->form)->assertRedirect();

        Http::assertSent(function ($request) {
            $text = $request['text'] ?? '';

            return str_contains($request->url(), '111:AAsignup')
                && ($request['chat_id'] ?? null) === '-100500'
                && str_contains($text, 'Fidan')
                && str_contains($text, '+994 50 123 45 67')
                && str_contains($text, 'fidan@nefis.az')
                && str_contains($text, 'Yeni qeydiyyat');
        });
    }

    public function test_nothing_is_sent_when_the_bot_is_not_set_up(): void
    {
        Http::fake();

        $this->post(route('register'), $this->form)->assertRedirect();

        Http::assertNothingSent();
        $this->assertDatabaseHas('users', ['email' => 'fidan@nefis.az']);
    }

    public function test_a_bot_that_is_down_does_not_stop_the_registration(): void
    {
        $this->botIsSetUp();
        Http::fake(['api.telegram.org/*' => Http::response('nope', 500)]);

        $this->post(route('register'), $this->form)->assertRedirect();

        // What matters is the account, not the message.
        $this->assertDatabaseHas('users', ['email' => 'fidan@nefis.az']);
        $this->assertTrue(auth()->check());
    }

    public function test_the_signup_bot_falls_back_to_the_shops_own(): void
    {
        Telegram::saveToken('222:AAshop');
        Setting::put(Setting::TELEGRAM_SIGNUP_CHAT, '-100777');

        $this->assertSame('222:AAshop', Telegram::signupToken());
        $this->assertTrue(Telegram::signupOn());
    }

    public function test_the_token_is_kept_encrypted(): void
    {
        Telegram::saveSignupToken('333:AAsecret');

        $this->assertNotSame('333:AAsecret', Setting::get(Setting::TELEGRAM_SIGNUP_TOKEN));
        $this->assertSame('333:AAsecret', Telegram::signupToken());
    }

    public function test_the_owner_sets_the_bot_up_on_the_settings_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->fillForm(['telegram_signup_token' => '444:AAfromform', 'telegram_signup_chat' => '-100999'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('444:AAfromform', Telegram::signupToken());
        $this->assertSame('-100999', Telegram::signupChat());
    }
}
