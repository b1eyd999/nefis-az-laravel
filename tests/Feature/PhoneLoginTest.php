<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * People remember their phone, not the address they signed up with, so the
 * door opens to either. A phone is compared by its own nine digits, however
 * it was typed; a password is still a password, and guessing at one stops
 * after five tries.
 */
class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private function customer(?string $phone = '+994 55 123 45 67'): User
    {
        return User::create([
            'name' => 'Aysel',
            'email' => 'aysel@nefis.az',
            'phone' => $phone,
            'password' => Hash::make('cox-gizli-sifre'),
        ]);
    }

    public function test_the_e_mail_still_opens_the_door(): void
    {
        $user = $this->customer();

        $this->post(route('login'), ['login' => 'aysel@nefis.az', 'password' => 'cox-gizli-sifre'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_phone_opens_it_too_however_it_is_written(): void
    {
        $user = $this->customer();

        foreach (['+994551234567', '0551234567', '55 123 45 67', '(055) 123-45-67'] as $typed) {
            $this->post(route('login'), ['login' => $typed, 'password' => 'cox-gizli-sifre'])->assertRedirect();
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    public function test_a_wrong_password_opens_nothing(): void
    {
        $this->customer();

        $this->post(route('login'), ['login' => '0551234567', 'password' => 'yanlis'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_a_phone_two_people_share_opens_nothing(): void
    {
        $this->customer();
        User::create([
            'name' => 'Nigar',
            'email' => 'nigar@nefis.az',
            'phone' => '+994 55 123 45 67',
            'password' => Hash::make('cox-gizli-sifre'),
        ]);

        $this->post(route('login'), ['login' => '0551234567', 'password' => 'cox-gizli-sifre'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_guessing_stops_after_five_tries(): void
    {
        $this->customer();
        RateLimiter::clear('login:0551234567|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), ['login' => '0551234567', 'password' => 'yanlis' . $i]);
        }

        $this->post(route('login'), ['login' => '0551234567', 'password' => 'cox-gizli-sifre'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_the_form_asks_for_either(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('E-poçt və ya telefon')
            ->assertSee('name="login"', false);
    }
}
