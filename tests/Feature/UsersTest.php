<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsersTest extends TestCase
{
    use RefreshDatabase;

    private function panel(User $as): void
    {
        $this->actingAs($as);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_role_and_the_old_admin_flag_stay_in_step(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->assertSame(User::ADMIN, $owner->role);

        $owner->update(['role' => User::MANAGER]);
        $this->assertFalse($owner->fresh()->is_admin);

        $this->post(route('register'), [
            'name' => 'Aysel', 'email' => 'aysel@example.test', 'phone' => '+994500000000',
            'password' => 'secret-pass-123', 'password_confirmation' => 'secret-pass-123',
        ]);
        $this->assertSame(User::CUSTOMER, User::where('email', 'aysel@example.test')->value('role'));
    }

    public function test_a_manager_sees_the_orders_and_nothing_else(): void
    {
        $manager = User::factory()->create(['role' => User::MANAGER]);
        $customer = User::factory()->create();
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        $this->actingAs($manager)->get('/admin/orders')->assertOk();
        foreach (['/admin/products', '/admin/scenes', '/admin/chocolates', '/admin/markets', '/admin/users'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
        $this->actingAs($manager)->get(route('box.edit', $box->slug))->assertForbidden();
        $this->actingAs($manager)->get(route('home'))->assertSee('Admin panel');

        $this->actingAs($customer)->get('/admin/orders')->assertForbidden();
        $this->actingAs($customer)->get(route('home'))->assertDontSee('Admin panel');
    }

    public function test_the_admin_sees_the_users_and_gives_them_roles(): void
    {
        $owner = User::factory()->create(['is_admin' => true, 'name' => 'Sahib']);
        $aysel = User::factory()->create(['name' => 'Aysel', 'phone' => '+994 50 111 22 33']);
        Order::create(['user_id' => $aysel->id, 'status' => 'pending', 'contact_phone' => '1', 'delivery_address' => 'Bakı']);
        $this->panel($owner);

        $this->get('/admin/users')->assertOk()->assertSee(['Aysel', '+994 50 111 22 33', 'Müştəri', 'Admin']);

        Livewire::test(ListUsers::class)
            ->callTableAction('role', $aysel, ['role' => User::MANAGER])
            ->assertHasNoTableActionErrors();
        $this->assertSame(User::MANAGER, $aysel->fresh()->role);

        // Her orders are listed on her page.
        $this->get('/admin/users/' . $aysel->id . '/edit')->assertOk()->assertSee('Menecer');
    }

    public function test_an_admin_cannot_lock_themselves_out(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->panel($owner);

        Livewire::test(ListUsers::class)->callTableAction('role', $owner, ['role' => User::CUSTOMER]);
        $this->assertSame(User::ADMIN, $owner->fresh()->role);

        Livewire::test(EditUser::class, ['record' => $owner->getRouteKey()])
            ->fillForm(['role' => User::MANAGER])
            ->call('save');
        $this->assertSame(User::ADMIN, $owner->fresh()->role);

        // Another admin may take it away, while one admin is left.
        $second = User::factory()->create(['is_admin' => true]);
        Livewire::test(ListUsers::class)->callTableAction('role', $second, ['role' => User::CUSTOMER]);
        $this->assertSame(User::CUSTOMER, $second->fresh()->role);
    }

    public function test_the_panel_offers_a_way_back_to_the_shop(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $this->get('/admin')->assertOk()
            ->assertSee('Sayta qayıt')
            ->assertSee('href="' . url('/') . '"', false);
    }

    /**
     * A customer writes that he cannot sign in. The owner gives him a new
     * password from the panel and sends it on himself.
     */
    public function test_the_owner_gives_a_customer_a_new_password(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $aysel = User::factory()->create(['name' => 'Aysel', 'password' => Hash::make('kohne-sifre')]);
        $this->panel($owner);

        Livewire::test(ListUsers::class)
            ->callTableAction('password', $aysel, ['password' => 'yeni-sifre-9'])
            ->assertHasNoTableActionErrors();

        $aysel->refresh();
        $this->assertTrue(Hash::check('yeni-sifre-9', $aysel->password));
        $this->assertFalse(Hash::check('kohne-sifre', $aysel->password), 'the old one stops working');
    }

    public function test_a_password_too_short_to_be_one_is_refused(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $aysel = User::factory()->create(['password' => Hash::make('kohne-sifre')]);
        $this->panel($owner);

        Livewire::test(ListUsers::class)
            ->callTableAction('password', $aysel, ['password' => 'qisa'])
            ->assertHasTableActionErrors(['password']);

        $this->assertTrue(Hash::check('kohne-sifre', $aysel->fresh()->password));
    }

    /** The one it offers can be read down a telephone without spelling it out. */
    public function test_the_offered_password_has_no_letter_that_could_be_a_digit(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $made = \App\Filament\Resources\UserResource::readablePassword();
            $this->assertSame(10, strlen($made));
            $this->assertMatchesRegularExpression('~^[a-zA-Z2-9]+$~', $made);
            $this->assertDoesNotMatchRegularExpression('~[OIl01]~', $made);
        }
    }

    public function test_a_manager_cannot_reach_the_users_at_all(): void
    {
        $manager = User::factory()->create(['role' => User::MANAGER, 'is_admin' => false]);
        $this->panel($manager);

        $this->get('/admin/users')->assertForbidden();
    }
}
