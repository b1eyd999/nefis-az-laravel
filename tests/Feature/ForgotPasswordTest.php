<?php

namespace Tests\Feature;

use App\Mail\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A forgotten password: asked for by e-mail, set with a link that is good
 * for an hour, and never a way to find out who shops here.
 */
class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function customer(): User
    {
        return User::factory()->create([
            'name' => 'Aysel', 'email' => 'aysel@example.com', 'password' => Hash::make('kohne-sifre-123'),
        ]);
    }

    public function test_the_login_page_offers_the_way_out(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Şifrəni unutmusunuz?')
            ->assertSee(route('password.request'), false);
    }

    public function test_a_letter_goes_out_and_the_link_sets_a_new_password(): void
    {
        Mail::fake();
        $user = $this->customer();

        $this->post(route('password.email'), ['email' => 'AYSEL@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $token = null;
        Mail::assertSent(PasswordReset::class, function (PasswordReset $mail) use ($user, &$token) {
            $token = $mail->token;

            return $mail->hasTo($user->email);
        });

        $this->assertNotNull($token);
        $this->assertDatabaseCount('password_reset_tokens', 1);
        // The token itself is kept hashed, the way a password is.
        $this->assertNotSame($token, DB::table('password_reset_tokens')->value('token'));

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Yeni şifrə təyin edin');

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'yeni-sifre-2026', 'password_confirmation' => 'yeni-sifre-2026',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue(Hash::check('yeni-sifre-2026', $user->fresh()->password));
        // Used once and gone.
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_an_unknown_address_is_answered_exactly_like_a_known_one(): void
    {
        Mail::fake();

        $known = $this->get(route('password.request'))->assertOk();
        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertNotNull($known);
    }

    public function test_a_wrong_or_stale_link_is_refused(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.update'), [
            'token' => 'this-is-not-the-token', 'email' => $user->email,
            'password' => 'yeni-sifre-2026', 'password_confirmation' => 'yeni-sifre-2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('kohne-sifre-123', $user->fresh()->password));
        $this->assertGuest();

        // An hour later the real token is no good either.
        $token = null;
        Mail::assertSent(PasswordReset::class, function (PasswordReset $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });
        DB::table('password_reset_tokens')->update(['created_at' => now()->subHours(2)]);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'yeni-sifre-2026', 'password_confirmation' => 'yeni-sifre-2026',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('kohne-sifre-123', $user->fresh()->password));
    }

    public function test_the_door_is_not_a_place_to_hammer(): void
    {
        Mail::fake();
        $user = $this->customer();

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasErrors('email');
    }

    public function test_a_short_new_password_is_refused(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->post(route('password.email'), ['email' => $user->email]);
        $token = null;
        Mail::assertSent(PasswordReset::class, function (PasswordReset $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'qisa', 'password_confirmation' => 'qisa',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('kohne-sifre-123', $user->fresh()->password));
    }
}
