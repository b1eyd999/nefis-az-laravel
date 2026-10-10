<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Chocolate;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Support\Cart;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A box dropped into a customer's basket from the users list.
 *
 * «Hazır səbətlər» is the long way round — the owner builds the box on the
 * site and hands over a link. This is the short one: somebody has signed up
 * and is on the telephone, and the owner puts a design in his basket while
 * they talk. He opens the shop and it is there.
 */
class BasketFromUsersListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    }

    private function customer(): User
    {
        return User::factory()->create([
            'email' => 'aygun@example.com',
            'password' => Hash::make('chocolate8'),
        ]);
    }

    private function box(string $slug = 'love-story'): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => $slug, 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function drop(User $customer, array $data): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($this->admin())
            ->test(ListUsers::class)
            ->callTableAction('basket', $customer, $data)
            ->assertHasNoTableActionErrors();
    }

    public function test_the_owner_drops_a_design_into_a_customers_basket(): void
    {
        $box = $this->box();
        $customer = $this->customer();

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null, 'quantity' => 2]);

        $saved = SavedCart::sole();
        $this->assertSame($customer->id, $saved->user_id);
        $this->assertCount(1, $saved->lines());
        $this->assertSame($box->id, $saved->lines()[0]['product_id']);
        $this->assertSame(2, $saved->lines()[0]['quantity']);

        /* He signs in on a telephone that knows nothing about any of this.
           Flushing the session is not enough: the guard in this process is
           still holding the admin who dropped the box in. */
        $this->flushSession();
        app('auth')->forgetGuards();
        $this->assertGuest();

        $this->post(route('login'), ['login' => 'aygun@example.com', 'password' => 'chocolate8'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Cart::count());
        $this->get(route('cart.index'))->assertOk()->assertSee('Love Story');
    }

    public function test_what_he_already_had_stays(): void
    {
        $his = $this->box('kinder');
        $given = $this->box('love-story');
        $customer = $this->customer();

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $his->id]);
        $this->assertSame(1, Cart::count());

        $this->drop($customer, ['product_id' => $given->id, 'chocolate_id' => null, 'quantity' => 1]);

        $this->assertCount(2, SavedCart::sole()->lines());
    }

    public function test_the_chosen_bar_travels_with_it(): void
    {
        $box = $this->box();
        $customer = $this->customer();
        $bar = Chocolate::create(['name' => 'Milka 90 q', 'weight_g' => 90, 'base_price' => 3.00]);

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => $bar->id, 'quantity' => 1]);

        $line = SavedCart::sole()->lines()[0];
        $this->assertSame($bar->id, $line['chocolate']['id']);
        $this->assertSame('Milka 90 q', $line['chocolate']['name']);
        $this->assertGreaterThan(0, $line['chocolate']['price'], 'the bar is priced the way the shop prices it');
    }

    /** Every key the basket reads is there, so nothing downstream has to guess. */
    public function test_the_line_looks_like_one_a_customer_made(): void
    {
        $box = $this->box();
        $customer = $this->customer();

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null, 'quantity' => 1]);

        $line = SavedCart::sole()->lines()[0];
        foreach (['id', 'product_id', 'quantity', 'photo_paths', 'custom_texts', 'photo_labels',
            'text_labels', 'photo_frames', 'star', 'spot', 'chocolate', 'wrapping', 'letter', 'ar', 'spotify'] as $key) {
            $this->assertArrayHasKey($key, $line, $key . ' is missing from the line');
        }
        $this->assertNotEmpty($line['id']);
    }

    public function test_a_full_basket_is_not_pushed_over_the_limit(): void
    {
        $box = $this->box();
        $customer = $this->customer();

        $full = [];
        for ($i = 0; $i < SavedCart::MOST; $i++) {
            $full[] = SavedCart::line($box->id) + ['id' => 'his-' . $i];
        }
        SavedCart::create(['user_id' => $customer->id, 'items' => $full, 'rush' => false]);

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null, 'quantity' => 1]);

        $this->assertCount(SavedCart::MOST, SavedCart::sole()->lines());
    }

    public function test_a_manager_cannot_reach_the_users_list_at_all(): void
    {
        $this->customer();
        $manager = User::factory()->create(['role' => 'manager', 'is_admin' => false]);

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    }
}
