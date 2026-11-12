<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Gonderish;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The customer's WhatsApp through Göndəriş: «your order is in» when it is
 * placed, «paid» when the money lands — each once, and never in the way of
 * the order itself.
 */
class GonderishTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(): Order
    {
        $customer = User::factory()->create(['name' => 'Aysel Məmmədova', 'email' => 'aysel@example.com']);
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id,
            'contact_phone' => '050 123 45 67',
            'delivery_address' => 'Bakı, Nəsimi 1',
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    private function switchOn(bool $placed = true, bool $paid = true): void
    {
        Gonderish::saveKey('gnd_test_key');
        Setting::put(Setting::GONDERISH_PLACED, $placed);
        Setting::put(Setting::GONDERISH_PAID, $paid);
    }

    public function test_a_placed_order_writes_to_the_customer_once(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(['ok' => true, 'message' => ['id' => 1, 'status' => 'queued']], 201)]);
        $this->switchOn();

        $order = $this->placeOrder();

        Http::assertSent(fn (Request $r) => $r->url() === Gonderish::URL.'/messages'
            && $r->hasHeader('Authorization', 'Bearer gnd_test_key')
            && $r['to'] === '994501234567'
            && $r['ref'] === "nefis-{$order->id}-placed"
            && str_contains($r['text'], 'sifariş #'.$order->id));
        Http::assertSentCount(1);

        // The same moment again (a double click, a retried request) writes nothing new.
        Gonderish::placed($order);
        Http::assertSentCount(1);
    }

    public function test_confirmed_payment_writes_paid(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(['ok' => true], 201)]);
        $this->switchOn(placed: false);
        $order = $this->placeOrder();
        Http::assertNothingSent();

        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        Http::assertSent(fn (Request $r) => $r['ref'] === "nefis-{$order->id}-paid"
            && str_contains($r['text'], 'Ödəniş təsdiqləndi'));
    }

    public function test_switched_off_or_without_a_key_nothing_is_sent(): void
    {
        Http::fake();
        $this->placeOrder();
        Http::assertNothingSent();
    }

    public function test_göndəriş_being_down_does_not_break_the_order(): void
    {
        Http::fake(['gonderish.az/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('down')]);
        $this->switchOn();

        $order = $this->placeOrder();

        $this->assertTrue($order->exists);
        $this->assertCount(1, $order->items);
    }
}
