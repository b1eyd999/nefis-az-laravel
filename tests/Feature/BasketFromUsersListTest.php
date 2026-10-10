<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Models\Wrapping;
use App\Support\Letter;
use App\Support\LiveMaterials;
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

    public function test_the_wrapping_paper_travels_with_it(): void
    {
        $box = $this->box();
        $customer = $this->customer();
        $paper = Wrapping::create(['name' => 'Qızılı kağız', 'pattern' => 'wrappings/gold.png', 'price' => 2.00, 'is_active' => true]);

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null,
            'wrapping_id' => $paper->id, 'letter' => false, 'ar' => false, 'quantity' => 1]);

        $line = SavedCart::sole()->lines()[0];
        $this->assertSame($paper->id, $line['wrapping']['id']);
        $this->assertSame(2.00, (float) $line['wrapping']['price']);
    }

    /**
     * The letter and the live photo go in as the service and its price; the
     * words and the video come from the customer afterwards.
     */
    public function test_the_letter_and_the_live_photo_go_in_priced_but_empty(): void
    {
        $box = $this->box();
        $customer = $this->customer();

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null,
            'wrapping_id' => null, 'letter' => true, 'ar' => true, 'quantity' => 1]);

        $line = SavedCart::sole()->lines()[0];
        $this->assertSame(Letter::price(), (float) $line['letter']['price']);
        $this->assertNull($line['letter']['text']);
        $this->assertSame(LiveMaterials::price(), (float) $line['ar']['price']);
        $this->assertNull($line['ar']['video']);
    }

    /** All of it is counted the way the basket counts it. */
    public function test_the_basket_adds_the_extras_to_the_price(): void
    {
        $box = $this->box();                       // 4.90
        $customer = $this->customer();
        $paper = Wrapping::create(['name' => 'Kağız', 'pattern' => 'wrappings/plain.png', 'price' => 2.00, 'is_active' => true]);

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null,
            'wrapping_id' => $paper->id, 'letter' => true, 'ar' => true, 'quantity' => 1]);

        $this->flushSession();
        app('auth')->forgetGuards();
        $this->post(route('login'), ['login' => 'aygun@example.com', 'password' => 'chocolate8'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $line = Cart::items()[0];
        $this->assertSame(
            round(4.90 + 2.00 + Letter::price() + LiveMaterials::price(), 2),
            round(Cart::unitPrice($line, $box), 2),
        );
    }

    /**
     * The order has to be placeable. A live photo added here has no video
     * yet, and reading that key outright used to stop the checkout dead.
     */
    public function test_an_order_can_still_be_placed_from_such_a_basket(): void
    {
        $box = $this->box();
        $customer = $this->customer();
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);

        $this->drop($customer, ['product_id' => $box->id, 'chocolate_id' => null,
            'wrapping_id' => null, 'letter' => true, 'ar' => true, 'quantity' => 1]);

        $this->flushSession();
        app('auth')->forgetGuards();
        $this->post(route('login'), ['login' => 'aygun@example.com', 'password' => 'chocolate8'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $item = $order->items()->firstOrFail();
        $this->assertSame(Letter::price(), (float) $item->letter_price);
        $this->assertSame(LiveMaterials::price(), (float) $item->ar_price);
        // The live photo is on file, waiting for its video.
        $this->assertDatabaseHas('live_photos', ['order_item_id' => $item->id, 'video_path' => null]);
    }

    public function test_a_manager_cannot_reach_the_users_list_at_all(): void
    {
        $this->customer();
        $manager = User::factory()->create(['role' => 'manager', 'is_admin' => false]);

        $this->actingAs($manager)->get('/admin/users')->assertForbidden();
    }
}
