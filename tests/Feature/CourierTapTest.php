<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * An order that is ready goes to the couriers' group with a button under it.
 * Whoever is free taps it, and from then on his name stands under the address
 * — in Telegram and in the admin.
 */
class CourierTapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Telegram::saveToken('123:OWNER');
        Setting::put(Setting::TELEGRAM_CHAT, '555000');
        Telegram::saveCourierToken('999:COURIER');
        Setting::put(Setting::TELEGRAM_COURIER_CHAT, '-1001234');
    }

    private function order(): Order
    {
        $customer = User::factory()->create(['name' => 'Aysel']);
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 123 45 67',
            'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10',
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    private function tap(Order $order, array $from, ?string $secret = null): \Illuminate\Testing\TestResponse
    {
        $secret ??= Telegram::hookSecret();

        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', Telegram::hookSecret())
            ->postJson('/telegram/kuryer/' . $secret, [
                'callback_query' => ['id' => '4242', 'from' => $from, 'data' => 'take:' . $order->id],
            ]);
    }

    public function test_a_courier_takes_the_order_and_his_name_stays_under_it(): void
    {
        Http::fake([
            'api.telegram.org/*sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 777, 'chat' => ['id' => -1001234]]]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $order = $this->order();
        $order->forceFill(['status' => 'ready'])->save();

        // The card went out with a button, and the shop remembers the message.
        Http::assertSent(fn ($r) => str_contains($r->url(), '999:COURIER/sendMessage')
            && str_contains((string) $r['reply_markup'], 'take:' . $order->id)
            && str_contains((string) $r['reply_markup'], 'götürürəm'));
        $order->refresh();
        $this->assertSame(['-1001234', 777], [$order->courier_chat_id, $order->courier_message_id]);

        $this->tap($order, ['first_name' => 'Elvin', 'last_name' => 'Quliyev'])->assertOk();

        $order->refresh();
        $this->assertSame('Elvin Quliyev', $order->courier_name);
        $this->assertNotNull($order->courier_taken_at);

        // The message in the group is written again, now with his name and no button.
        Http::assertSent(fn ($r) => str_contains($r->url(), 'editMessageText')
            && $r['message_id'] === 777
            && str_contains((string) $r['text'], 'Götürdü:')
            && str_contains((string) $r['text'], 'Elvin Quliyev'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'answerCallbackQuery'));
    }

    public function test_the_second_courier_is_told_it_is_taken(): void
    {
        Http::fake([
            'api.telegram.org/*sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 5, 'chat' => ['id' => -1001234]]]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
        $order = $this->order();
        $order->forceFill(['status' => 'ready'])->save();
        $this->tap($order, ['first_name' => 'Elvin'])->assertOk();

        $this->tap($order, ['username' => 'vuqar'])->assertOk();

        $this->assertSame('Elvin', $order->fresh()->courier_name, 'the first one keeps it');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'answerCallbackQuery')
            && str_contains((string) $r['text'], 'artıq Elvin'));
    }

    public function test_a_stranger_knocking_on_the_address_gets_nothing(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1, 'chat' => ['id' => -1001234]]])]);
        $order = $this->order();
        $order->forceFill(['status' => 'ready'])->save();

        // The right address, but no secret header.
        $this->postJson('/telegram/kuryer/' . Telegram::hookSecret(), [
            'callback_query' => ['id' => '1', 'from' => ['first_name' => 'Nobody'], 'data' => 'take:' . $order->id],
        ])->assertNotFound();

        // A made-up address.
        $this->tap($order, ['first_name' => 'Nobody'], str_repeat('a', 32))->assertNotFound();

        $this->assertNull($order->fresh()->courier_name);
    }
}
