<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The customer's own page, and one password field instead of two.
 *
 * Signing up used to ask for the password twice — two fields of work on a
 * telephone, at the step where this shop loses people — and once somebody
 * was signed up there was nowhere to correct the number he had typed wrong.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_up_asks_for_the_password_once(): void
    {
        $this->post(route('register'), [
            'name' => 'Aygün Məmmədova',
            'email' => 'aygun@example.com',
            'phone' => '+994 55 123 45 67',
            'password' => 'chocolate8',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user = User::sole();
        $this->assertSame('Aygün Məmmədova', $user->name);
        $this->assertTrue(Hash::check('chocolate8', $user->password));
        $this->assertAuthenticatedAs($user);

        // And the field that used to ask again is gone from the page.
        $this->post(route('logout'));
        $this->get(route('register'))->assertOk()
            ->assertDontSee('password_confirmation', false)
            ->assertSee('pw-eye', false);
    }

    public function test_a_short_password_is_still_refused(): void
    {
        $this->post(route('register'), [
            'name' => 'Aygün',
            'email' => 'aygun@example.com',
            'phone' => '+994 55 123 45 67',
            'password' => 'short',
        ])->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_the_customer_opens_his_own_page_and_corrects_his_number(): void
    {
        $user = User::factory()->create([
            'name' => 'Aygün', 'email' => 'aygun@example.com', 'phone' => Contact::az('+994 55 123 45 67'),
        ]);

        $this->actingAs($user)->get(route('profile.index'))->assertOk()
            ->assertSee('Hesabım')
            ->assertSee('aygun@example.com')
            ->assertSee($user->phone);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => 'Aygün Məmmədova',
            'email' => 'aygun2@example.com',
            'phone' => '0552224466',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Aygün Məmmədova', $user->name);
        $this->assertSame('aygun2@example.com', $user->email);
        // Stored in the one shape the shop dials, however it was typed.
        $this->assertSame(Contact::az('+994 55 222 44 66'), $user->phone);
    }

    public function test_a_number_somebody_else_signs_in_with_is_refused(): void
    {
        $taken = Contact::az('+994 55 123 45 67');
        User::factory()->create(['phone' => $taken]);
        $user = User::factory()->create(['phone' => Contact::az('+994 55 999 88 77')]);

        $this->actingAs($user)->post(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'phone' => '+994 55 123 45 67',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(Contact::az('+994 55 999 88 77'), $user->fresh()->phone);
    }

    public function test_a_new_password_needs_the_old_one(): void
    {
        $user = User::factory()->create(['password' => Hash::make('chocolate8')]);

        $this->actingAs($user)->post(route('profile.password'), [
            'current_password' => 'not-it',
            'password' => 'newsecret9',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('chocolate8', $user->fresh()->password));

        $this->actingAs($user)->post(route('profile.password'), [
            'current_password' => 'chocolate8',
            'password' => 'newsecret9',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newsecret9', $user->fresh()->password));
    }

    public function test_the_page_belongs_to_whoever_is_signed_in(): void
    {
        $this->get(route('profile.index'))->assertRedirect(route('login'));
        $this->post(route('profile.update'), [])->assertRedirect(route('login'));
    }

    /** The way in is in both menus, so it can be found without the address. */
    public function test_the_menus_point_at_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertOk()
            ->assertSee(route('profile.index'), false)
            ->assertSee('Hesabım');
    }
}
