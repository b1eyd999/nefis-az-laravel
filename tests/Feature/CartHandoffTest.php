<?php

namespace Tests\Feature;

use App\Models\CartHandoff;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A basket the shop filled for a customer who could not fill it himself.
 *
 * Most of the shop's conversations begin in Instagram, and some of them stop
 * there: the customer sends his photograph, says what to write on the box,
 * and never gets through the design page. The owner builds the box himself
 * and sends one address; what opens is the customer's own basket.
 */
class CartHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function box(string $slug = 'love-story'): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => $slug, 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function owner(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_the_shop_builds_a_basket_and_gets_one_address_to_send(): void
    {
        $box = $this->box();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 3]);
        $this->assertSame(3, Cart::count());

        // The button is his alone: a customer standing on the same page
        // with the same basket is not offered it.
        $this->actingAs($owner)->get(route('cart.index'))->assertOk()->assertSee('Müştəri üçün hazır səbət');
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('cart.index'))->assertOk()->assertDontSee('Müştəri üçün hazır səbət');
        $this->actingAs($owner);

        $this->actingAs($owner)
            ->post(route('cart.handoff.store'), ['note' => 'Aygün, Instagram'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $made = CartHandoff::sole();
        $this->assertSame('Aygün, Instagram', $made->note);
        $this->assertSame(3, $made->pieces());
        $this->assertSame($owner->id, $made->created_by);
        $this->assertStringContainsString('/hazir-sebet/', $made->url());
        $this->assertTrue($made->isOpen());

        // And the address is on the page he lands back on, ready to copy.
        $this->actingAs($owner)->get(route('cart.index'))->assertOk()
            ->assertSee('Səbət hazırdır. Linki müştəriyə göndərin.')
            ->assertSee($made->url());

        // His own basket goes with it — he built it for somebody else, and a
        // stranger's photograph must not follow him into the next one.
        $this->assertSame(0, Cart::count());
    }

    public function test_the_customer_opens_the_address_and_the_basket_is_his(): void
    {
        $box = $this->box();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 2]);
        $this->actingAs($owner)->post(route('cart.handoff.store'), ['note' => 'Aygün']);
        $made = CartHandoff::sole();

        // Somebody else entirely, not signed in at all.
        $this->flushSession();

        $this->get($made->url())
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('status');

        $this->assertSame(2, Cart::count());
        $this->get(route('cart.index'))->assertOk()->assertSee('Love Story');

        $this->assertNotNull($made->fresh()->opened_at);
    }

    public function test_only_the_shop_may_turn_a_basket_into_an_address(): void
    {
        $box = $this->box();
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($customer)->post(route('cart.handoff.store'))->assertForbidden();

        $this->assertSame(0, CartHandoff::count());
    }

    public function test_an_empty_basket_makes_no_address(): void
    {
        $this->actingAs($this->owner())
            ->post(route('cart.handoff.store'))
            ->assertSessionHasErrors('handoff');

        $this->assertSame(0, CartHandoff::count());
    }

    public function test_an_address_that_has_run_out_says_so_rather_than_opening(): void
    {
        $box = $this->box();
        $owner = $this->owner();
        $this->actingAs($owner)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($owner)->post(route('cart.handoff.store'));

        $made = CartHandoff::sole();
        $made->forceFill(['expires_at' => now()->subDay()])->save();

        $this->flushSession();
        $this->get($made->url())->assertRedirect(route('cart.index'))->assertSessionHasErrors('handoff');
        $this->assertSame(0, Cart::count());
    }

    public function test_the_order_it_became_is_written_on_it_and_it_opens_no_more(): void
    {
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);
        $box = $this->box();
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($owner)->post(route('cart.handoff.store'), ['note' => 'Aygün']);
        $made = CartHandoff::sole();

        $customer = User::factory()->create(['is_admin' => false]);
        $this->flushSession();
        $this->actingAs($customer)->get($made->url());

        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '+994 55 123 45 67',
            'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->sole();
        $this->assertSame($order->id, $made->fresh()->order_id);
        $this->assertFalse($made->fresh()->isOpen());
        $this->assertSame('Sifariş verildi', $made->fresh()->stateLabel());

        // And the same address cannot be ordered a second time by accident.
        $this->flushSession();
        $this->get($made->url())->assertSessionHasErrors('handoff');
        $this->assertSame(0, Cart::count());
    }
}
