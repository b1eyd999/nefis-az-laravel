<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A basket big enough carries its own delivery.
 *
 * A promise the shop makes before the order, worked out from the goods — as
 * against the waiver in [[FreeDeliveryTest]], which is the owner's own
 * decision on one order after the fact. Both end in the same flag, so the
 * method and its price stay on the order either way.
 */
class FreeDeliveryFromASumTest extends TestCase
{
    use RefreshDatabase;

    private function paidDoor(): DeliveryMethod
    {
        $method = DeliveryMethod::where('type', 'door')->firstOrFail();
        $method->forceFill(['price' => 5])->save();

        return $method;
    }

    private function box(float $price): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . $price, 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function order(Product $box, int $quantity = 1, ?string $promo = null): Order
    {
        $user = User::factory()->create();
        PaymentAccount::firstOrCreate(['type' => PaymentAccount::CARD],
            ['label' => 'Kart', 'number' => '4169738111111111']);
        $door = $this->paidDoor();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        if ($quantity > 1) {
            $this->actingAs($user)->patch(route('cart.quantity', \App\Support\Cart::items()[0]['id']),
                ['quantity' => $quantity]);
        }
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id,
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => $promo,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_nothing_is_given_away_until_the_owner_names_a_sum(): void
    {
        $this->assertNull(DeliveryMethod::freeFrom());
        $this->assertFalse(DeliveryMethod::freeOn(1000));

        $order = $this->order($this->box(20));
        $this->assertFalse((bool) $order->free_delivery);
        $this->assertSame(5.0, $order->deliveryCharged());
    }

    public function test_a_basket_over_the_sum_travels_free(): void
    {
        Setting::put(Setting::FREE_DELIVERY_FROM, '50');

        $under = $this->order($this->box(20), 2);          // 40 ₼
        $this->assertFalse((bool) $under->free_delivery);
        $this->assertSame(5.0, $under->deliveryCharged());
        $this->assertSame(45.0, $under->total());

        $over = $this->order($this->box(30), 2);           // 60 ₼
        $this->assertTrue((bool) $over->free_delivery);
        $this->assertSame(0.0, $over->deliveryCharged());
        $this->assertSame(60.0, $over->total(), 'the delivery is off the bill');
        $this->assertSame(5.0, (float) $over->delivery_price, 'what the run was worth is still on the order');
    }

    /** Exactly the sum is enough — "from 50 ₼" includes 50. */
    public function test_the_sum_itself_counts(): void
    {
        Setting::put(Setting::FREE_DELIVERY_FROM, '50');
        $this->assertTrue(DeliveryMethod::freeOn(50));
        $this->assertFalse(DeliveryMethod::freeOn(49.99));

        $order = $this->order($this->box(25), 2);
        $this->assertTrue((bool) $order->free_delivery);
    }

    /**
     * The goods decide it, before the discount is taken off.
     *
     * A customer who fills a 60 ₼ basket has earned it; a code he then types
     * in should not take it away again, or the promise reads as a trap.
     */
    public function test_a_promo_code_does_not_take_the_free_delivery_back(): void
    {
        Setting::put(Setting::FREE_DELIVERY_FROM, '50');
        PromoCode::create(['code' => 'ON', 'percent' => 30, 'is_active' => true]);

        $order = $this->order($this->box(30), 2, 'ON');   // 60 ₼ − 18 ₼
        $this->assertSame(18.0, round((float) $order->discount, 2));
        $this->assertTrue((bool) $order->free_delivery);
        $this->assertSame(42.0, $order->total());
    }

    /** What the pages say about it. */
    public function test_the_basket_and_the_checkout_say_how_near_it_is(): void
    {
        Setting::put(Setting::FREE_DELIVERY_FROM, '50');
        $this->paidDoor();
        $user = User::factory()->create();
        $box = $this->box(20);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);

        // 20 ₼ in the basket: 30 ₼ to go, and the checkout says the same.
        $this->actingAs($user)->get(route('cart.index'))->assertOk()->assertSee('30 ₼');
        $this->actingAs($user)->get(route('checkout.index'))->assertOk()
            ->assertSee('30 ₼')
            ->assertSee('deliveryFree = false', false);

        // Three of them and it is earned.
        $this->actingAs($user)->patch(route('cart.quantity', \App\Support\Cart::items()[0]['id']),
            ['quantity' => 3]);
        $this->actingAs($user)->get(route('cart.index'))->assertOk()
            ->assertSee(__('Çatdırılma bizdən.'));
        $this->actingAs($user)->get(route('checkout.index'))->assertOk()
            ->assertSee('deliveryFree = true', false)
            ->assertSee('<s>5 ₼</s>', false);
    }

    /** The owner sets it in the panel. */
    public function test_the_owner_names_the_sum_in_the_panel(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\SiteSettings::class)
            ->fillForm(['free_delivery_from' => 75])
            ->call('save');

        $this->assertSame(75.0, DeliveryMethod::freeFrom());
    }
}
