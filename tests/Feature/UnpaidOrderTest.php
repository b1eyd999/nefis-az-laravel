<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Placing an order empties the basket. A customer who opened the payment page,
 * left it and came back to the shop then found nothing anywhere — no basket,
 * no sign of the order — and decided it had vanished. Every page now carries
 * him back to it.
 */
class UnpaidOrderTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $user): Order
    {
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_an_unpaid_order_follows_the_customer_around_the_shop(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        foreach (['/', route('designs.index'), route('cart.index')] as $page) {
            $this->actingAs($user)->get($page)->assertOk()
                ->assertSee('unpaid-bar', false)
                ->assertSee('#' . $order->id, false)
                ->assertSee(route('orders.pay', $order), false);
        }

        // The basket really is empty — that is why the line is needed.
        $this->actingAs($user)->get(route('cart.index'))->assertSee('Səbətiniz hələ boşdur', false);
    }

    public function test_it_is_not_shown_on_the_payment_page_itself(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()
            ->assertDontSee('unpaid-bar', false);
    }

    public function test_it_goes_away_once_the_order_is_paid(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        $this->actingAs($user)->get('/')->assertOk()->assertDontSee('unpaid-bar', false);
        $this->assertNull(Order::unpaidFor($user));
    }

    public function test_nobody_else_is_told_about_it(): void
    {
        $user = User::factory()->create();
        $this->order($user);

        // actingAs() keeps the customer signed in for the rest of the test.
        \Illuminate\Support\Facades\Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get('/')->assertOk()->assertDontSee('unpaid-bar', false);
        $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertDontSee('unpaid-bar', false);
        $this->assertNull(Order::unpaidFor(null));
    }
}
