<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A refused cancellation is answered on his own page, in words.
 *
 * It used to end at «403 Forbidden» on a white screen — tapping the button
 * twice did it, and so did coming back from the bank with the payment still
 * in flight. A customer who has just tried to cancel an order and been shown
 * an error code does not know whether it was cancelled or not.
 */
class CancellingIsAnsweredInWordsTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user, array $over = []): Order
    {
        $box = Product::firstWhere('slug', 'test') ?? tap(Product::create([
            'name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895,
        ]), fn ($b) => $b->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp',
            'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895, 'rotation' => 0,
            'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]));

        $order = Order::create(array_replace([
            'user_id' => $user->id,
            'status' => 'awaiting_payment',
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_type' => 'door', 'delivery_name' => 'Qapıya', 'delivery_price' => 0,
            'contact_phone' => '1', 'delivery_address' => 'Bakı',
        ], $over));
        $order->items()->create(['product_id' => $box->id, 'product_name' => 'Test',
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1]);

        return $order->fresh();
    }

    public function test_he_cancels_his_own_order(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('status', __('Sifariş ləğv edildi.'));

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /** Twice is not an error: the second tap says so plainly. */
    public function test_cancelling_twice_says_so(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->post(route('orders.cancel', $order));
        $this->actingAs($user)->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('status', __('Bu sifariş artıq ləğv edilib.'));
    }

    /** Back from the bank with the payment in flight: a sentence, not a 403. */
    public function test_a_payment_in_flight_is_explained(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        $order->forceFill(['payment_started_at' => now()])->save();

        $this->actingAs($user)->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('error', __('Ödəniş yoxlanılır. Bir neçə dəqiqədən sonra yenidən cəhd edin.'));

        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }

    /** A paid order is not cancelled by a button, and he is told why. */
    public function test_a_paid_order_is_explained(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user, ['status' => 'confirmed']);
        $order->forceFill(['payment_confirmed_at' => now()])->save();

        $this->actingAs($user)->post(route('orders.cancel', $order))
            ->assertRedirect(route('orders.index'))
            ->assertSessionHas('error', __('Bu sifariş ödənilib — ləğv etmək üçün bizə yazın.'));
    }

    /** Somebody else's order is still a flat refusal. */
    public function test_another_customers_order_is_refused(): void
    {
        $order = $this->order(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('orders.cancel', $order))->assertForbidden();

        $this->assertSame('awaiting_payment', $order->fresh()->status);
    }
}
