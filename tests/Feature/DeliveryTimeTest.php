<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\DeliveryTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Every box is made by hand, so nothing is ready the same day: checkout says
 * so and only lets the customer pick a day from there on.
 */
class DeliveryTimeTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private DeliveryMethod $door;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->customer = User::factory()->create();

        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $this->door->update(['price' => 5, 'is_active' => true]);

        $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->customer)->post(route('checkout.store'), $fields + [
            'delivery_method_id' => $this->door->id,
            'contact_phone' => '+994 50 123 45 67',
            'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10',
            'delivery_date' => '2026-09-26',
            'delivery_slot' => '14:00–18:00',
        ]);
    }

    public function test_checkout_says_when_the_box_can_be_ready_and_offers_the_times(): void
    {
        $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk()
            ->assertSee('sifariş 2 gündən sonra hazır olur')
            ->assertSee('Ən tez 26 sentyabr, şənbə tarixinə çatdıra bilərik')
            ->assertSee('min="2026-09-26"', false)
            ->assertSee('10:00–14:00')
            ->assertSee('18:00–21:00');
    }

    public function test_an_earlier_day_is_refused_and_the_chosen_one_is_kept(): void
    {
        $this->order(['delivery_date' => '2026-09-25'])->assertSessionHasErrors('delivery_date');
        $this->order(['delivery_slot' => 'gecə yarısı'])->assertSessionHasErrors('delivery_slot');
        $this->assertSame(0, Order::count(), 'nothing is placed until the day works');

        $this->order()->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('2026-09-26', $order->delivery_date->toDateString());
        $this->assertSame('14:00–18:00', $order->delivery_slot);

        // The customer sees it back on his orders page.
        $this->actingAs($this->customer)->get(route('orders.index'))->assertOk()
            ->assertSee('26 sentyabr, şənbə')
            ->assertSee('14:00–18:00');
    }

    public function test_the_admin_carries_the_day_where_a_phone_can_see_it(): void
    {
        $this->order()->assertRedirect();
        $order = Order::firstOrFail();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));

        // The list has no column of its own for it on a phone: the day rides
        // under the money, and the number under the customer's name.
        $this->get('/admin/orders')->assertOk()
            ->assertSee('26.09.2026')
            ->assertSee('14:00–18:00')        // the slot as one short word, so the row fits
            ->assertSee('#' . $order->id);

        $this->get('/admin/orders/' . $order->id . '/edit')->assertOk()
            ->assertSee('Nə vaxta')
            ->assertSee('26 sentyabr, şənbə, 14:00–18:00');
    }

    public function test_the_owner_changes_how_long_a_box_takes(): void
    {
        Setting::put(Setting::DELIVERY_LEAD_DAYS, 5);
        Setting::put(Setting::DELIVERY_SLOTS, "09:00–13:00\n13:00–17:00");

        $this->assertSame('2026-09-29', DeliveryTime::earliest()->toDateString());
        $this->assertSame(['09:00–13:00', '13:00–17:00'], DeliveryTime::slots());

        $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk()
            ->assertSee('sifariş 5 gündən sonra hazır olur')
            ->assertSee('09:00–13:00')
            ->assertDontSee('18:00–21:00');

        $this->order()->assertSessionHasErrors('delivery_date');   // 26th is too soon now
    }
}
