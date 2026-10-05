<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\Product;
use App\Models\User;
use App\Support\Accounting;
use App\Support\OrderEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner waives the delivery on an order.
 *
 * It is his decision, taken after the order exists: the checkout never offers
 * it and never mentions it. The method and its price stay on the order — only
 * what the customer is charged for it changes — so it can be undone and the
 * shop can still see what the courier run was worth.
 */
class FreeDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** The seeded door delivery is free; this feature is about one that costs. */
    private function paidDoor(): DeliveryMethod
    {
        $method = DeliveryMethod::where('type', 'door')->firstOrFail();
        $method->forceFill(['price' => 5])->save();

        return $method;
    }

    private function order(?User $user = null): Order
    {
        $user ??= User::factory()->create();
        $this->paidDoor();
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_waiving_it_drops_the_price_and_keeps_what_the_run_was_worth(): void
    {
        $order = $this->order();
        $paid = (float) $order->delivery_price;
        $this->assertGreaterThan(0, $paid, 'the door delivery costs something to begin with');
        $was = $order->total();

        $order->forceFill(['free_delivery' => true])->save();
        $order = $order->fresh()->load('items');

        $this->assertSame(0.0, $order->deliveryCharged());
        $this->assertSame(round($was - $paid, 2), round($order->total(), 2));
        // The method and its price are still on the order.
        $this->assertSame($paid, (float) $order->delivery_price);
        $this->assertNotNull($order->delivery_name);
    }

    public function test_the_customer_is_never_offered_it_at_the_checkout(): void
    {
        $user = User::factory()->create();
        $box = Product::create(['name' => 'Test', 'slug' => 'test2', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);

        $this->actingAs($user)->get(route('checkout.index'))->assertOk()
            ->assertDontSee('free_delivery')
            ->assertDontSee('pulsuz çatdırılma', false);

        // And posting it does not make it so: it is not a field of the checkout.
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'free_delivery' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) Order::latest('id')->first()->free_delivery);
    }

    public function test_on_a_paid_order_it_becomes_money_owed_back(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now(),
            'payment_asked_for' => $order->total()])->save();
        $paid = (float) $order->delivery_price;

        $money = OrderEditor::change($order->fresh(), 'Çatdırılma pulsuz edildi',
            fn () => $order->forceFill(['free_delivery' => true])->save());

        $this->assertNotNull($money);
        $this->assertSame(OrderAdjustment::REFUND, $money->kind);
        $this->assertSame($paid, round($money->amount, 2));
        // He paid the bigger figure and is owed the delivery back.
        $this->assertSame($paid, $order->fresh()->owedBack());
    }

    public function test_it_can_be_undone(): void
    {
        $order = $this->order();
        $paid = (float) $order->delivery_price;
        $full = $order->total();

        $order->forceFill(['free_delivery' => true])->save();
        $order->fresh()->forceFill(['free_delivery' => false])->save();

        $order = $order->fresh()->load('items');
        $this->assertSame($paid, $order->deliveryCharged());
        $this->assertSame(round($full, 2), round($order->total(), 2));
    }

    public function test_the_books_count_the_delivery_that_was_charged(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now(), 'free_delivery' => true])->save();

        $row = collect(Accounting::report()['rows'])->first(fn ($r) => $r['order']->id === $order->id);

        $this->assertNotNull($row);
        $this->assertSame(0.0, round((float) $row['delivery'], 2), 'a delivery nobody paid for is not revenue');
    }

    public function test_only_the_owner_sees_the_button(): void
    {
        $order = $this->order();

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/orders/' . $order->id . '/edit')->assertOk()
            ->assertSee('Çatdırılmanı pulsuz et');

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/orders/' . $order->id . '/edit')->assertOk()
            ->assertDontSee('Çatdırılmanı pulsuz et');
    }
}
