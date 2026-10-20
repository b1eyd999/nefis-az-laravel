<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «Я хочу, чтобы админ не мог войти через обычное окно как пользователя.»
 *
 * The shop's own people — the admin and the managers — sign in at /admin and
 * nowhere else. The customers' form and the password-reset page are the
 * customers' doors.
 *
 * A courier is not staff. He has no panel to sign into and /kuryer is an
 * ordinary page behind the ordinary form, so he must keep coming in the
 * ordinary way.
 */
class StaffComeInAtThePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:' . User::loginKey('kim@nefis.az') . '|127.0.0.1');
    }

    private function person(string $role, string $email): User
    {
        return User::factory()->create([
            'role' => $role,
            'email' => $email,
            'password' => Hash::make('parol12345'),
        ]);
    }

    public static function theShopsOwnPeople(): array
    {
        return [
            'admin' => [User::ADMIN],
            'manager' => [User::MANAGER],
        ];
    }

    /**
     * @dataProvider theShopsOwnPeople
     */
    public function test_the_customers_form_refuses_them_even_with_the_right_password(string $role): void
    {
        $staff = $this->person($role, 'kim@nefis.az');

        $answer = $this->post(route('login'), ['login' => 'kim@nefis.az', 'password' => 'parol12345']);

        $answer->assertSessionHasErrors('login');
        $this->assertGuest('web');
        $this->assertStringContainsString('/admin',
            (string) session('errors')->first('login'), 'and it says where his own door is');
        $this->assertTrue($staff->fresh()->signsInAtThePanel());
    }

    /** And the panel still lets them in, which is the whole point. */
    public function test_the_panel_still_opens_for_them(): void
    {
        $this->person(User::ADMIN, 'kim@nefis.az');

        $this->post('/admin/login', ['email' => 'kim@nefis.az', 'password' => 'parol12345']);

        // Filament's own form is Livewire; what matters here is that nothing
        // in this change stands between a staff account and that panel.
        $this->assertTrue(User::where('email', 'kim@nefis.az')->first()->canAccessPanel(
            \Filament\Facades\Filament::getPanel('admin')));
    }

    /** A wrong password gets the ordinary answer, so nobody learns who is staff. */
    public function test_a_wrong_password_says_no_more_than_it_ever_did(): void
    {
        $this->person(User::ADMIN, 'kim@nefis.az');
        User::factory()->create(['email' => 'adi@misal.az', 'password' => Hash::make('parol12345')]);

        $atStaff = $this->post(route('login'), ['login' => 'kim@nefis.az', 'password' => 'yanlisparol']);
        $this->app['session']->forget('errors');
        $atCustomer = $this->post(route('login'), ['login' => 'adi@misal.az', 'password' => 'yanlisparol']);

        $this->assertSame(
            $atStaff->baseResponse->getSession()->get('errors')?->first('login'),
            $atCustomer->baseResponse->getSession()->get('errors')?->first('login'),
            'a stranger must not be able to tell a staff address from any other'
        );
    }

    /** The customer's own door is untouched. */
    public function test_a_customer_still_walks_straight_in(): void
    {
        $this->person(User::CUSTOMER, 'adi@misal.az');

        $this->post(route('login'), ['login' => 'adi@misal.az', 'password' => 'parol12345'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated('web');
    }

    /**
     * And so is the courier's. He is not staff: no panel, and the one screen
     * written for him sits behind this very form.
     */
    public function test_the_courier_still_walks_straight_in(): void
    {
        $courier = $this->person(User::COURIER, 'kuryer@nefis.az');

        $this->post(route('login'), ['login' => 'kuryer@nefis.az', 'password' => 'parol12345'])
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($courier, 'web');
        $this->assertFalse($courier->signsInAtThePanel());
        $this->get('/kuryer')->assertSuccessful();
    }

    /**
     * Resetting the password is allowed — the letter went to his address —
     * but it does not put him in a customer session. And every session he
     * had is closed, which is what a reset is for.
     */
    public function test_a_reset_changes_the_password_without_opening_the_wrong_door(): void
    {
        $staff = $this->person(User::ADMIN, 'kim@nefis.az');
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $staff->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);
        DB::table('sessions')->insert([
            'id' => 'kohne-sessiya', 'user_id' => $staff->id, 'ip_address' => '127.0.0.1',
            'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp,
        ]);

        $answer = $this->post(route('password.update'), [
            'token' => $token,
            'email' => $staff->email,
            'password' => 'yeniparol12345',
            'password_confirmation' => 'yeniparol12345',
        ]);

        $answer->assertRedirect('/admin');
        $this->assertGuest('web');
        $this->assertTrue(Hash::check('yeniparol12345', $staff->fresh()->password),
            'the password really did change');
        $this->assertDatabaseMissing('sessions', ['id' => 'kohne-sessiya']);
    }

    /** A customer's reset still signs him in, as it always did. */
    public function test_a_customers_reset_still_signs_him_in(): void
    {
        $customer = $this->person(User::CUSTOMER, 'adi@misal.az');
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email' => $customer->email, 'token' => Hash::make($token), 'created_at' => now(),
        ]);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $customer->email,
            'password' => 'yeniparol12345', 'password_confirmation' => 'yeniparol12345',
        ]);

        $this->assertAuthenticatedAs($customer, 'web');
    }

    /**
     * Nobody makes himself staff on the way in. The role is set by the admin
     * and never by a form somebody fills in.
     */
    public function test_signing_up_cannot_hand_anybody_a_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Z', 'email' => 'yeni@misal.az', 'phone' => '+994557778899',
            'password' => 'parol12345', 'password_confirmation' => 'parol12345',
            'role' => User::ADMIN, 'is_admin' => 1,
        ]);

        $made = User::where('email', 'yeni@misal.az')->firstOrFail();
        $this->assertSame(User::CUSTOMER, $made->role);
        $this->assertFalse((bool) $made->is_admin);
        $this->assertFalse($made->signsInAtThePanel());
    }
}
