<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What Google Analytics is told about a sale: the basket, the checkout and
 * the order itself, so the reports can say which pages bring orders.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
    }

    private function box(float $price = 4.0): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** @return array<string, mixed> the parameters of one gtag event on the page */
    private function event(string $html, string $name): array
    {
        $this->assertMatchesRegularExpression("/gtag\('event', '{$name}'/", $html, "the page reports {$name}");
        preg_match("/gtag\('event', '{$name}', (\{.*?\})\);/s", $html, $m);

        return json_decode($m[1] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_a_sale_is_reported_step_by_step_and_the_order_only_once(): void
    {
        $box = $this->box(4.90);
        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        // Into the basket.
        $cart = $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasNoErrors();
        $added = $this->event($this->followed($cart), 'add_to_cart');
        $this->assertSame(4.9, $added['value']);
        $this->assertSame('AZN', $added['currency']);
        $this->assertSame('Love Story', $added['items'][0]['item_name']);

        // Opening checkout.
        $checkout = $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk()->getContent();
        $this->assertSame(4.9, $this->event($checkout, 'begin_checkout')['value']);

        // Placing the order.
        $done = $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1', 'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10',
        ]);
        $order = Order::firstOrFail();
        $purchase = $this->event($this->followed($done), 'purchase');

        $this->assertSame((string) $order->id, $purchase['transaction_id']);
        $this->assertSame(9.9, $purchase['value']);          // box 4.90 + delivery 5
        $this->assertEquals(5, $purchase['shipping']);   // JSON drops the trailing zero
        $this->assertSame('design-' . $box->id, $purchase['items'][0]['item_id']);

        // A refresh of the same page must not count the order again.
        $this->actingAs($this->customer)->get(route('orders.index'))->assertOk()
            ->assertDontSee("gtag('event', 'purchase'", false);
    }

    public function test_nothing_is_reported_while_the_counter_is_off(): void
    {
        Setting::put(Setting::SEO_ANALYTICS, '');
        $box = $this->box();

        $answer = $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id]);

        $this->assertStringNotContainsString("gtag('event'", $this->followed($answer));
    }

    /** The page the customer lands on after an action. */
    private function followed(\Illuminate\Testing\TestResponse $response): string
    {
        return $this->actingAs($this->customer)->get($response->headers->get('Location'))->assertOk()->getContent();
    }
}
