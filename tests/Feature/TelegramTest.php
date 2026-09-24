<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Telegram;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A new order reaches the owner's Telegram without him watching the admin.
 */
class TelegramTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['name' => 'Aysel']);
        Telegram::saveToken('123:ABC');
        Setting::put(Setting::TELEGRAM_CHAT, '555000');
    }

    private function order(): Order
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 123 45 67',
            'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10', 'note' => 'Zəng etməyin, sürpriz',
        ])->assertRedirect();

        return Order::firstOrFail();
    }

    public function test_a_new_order_is_written_to_the_owners_telegram(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $order = $this->order();

        Http::assertSent(function ($request) use ($order) {
            $text = $request['text'];

            return str_contains($request->url(), '/bot123:ABC/sendMessage')
                && $request['chat_id'] === '555000'
                && str_contains($text, 'Yeni sifariş #' . $order->id)
                && str_contains($text, 'Love Story × 1')
                && str_contains($text, 'Cəmi: 9.90 ₼')        // box 4.90 + delivery 5
                && str_contains($text, '+994 50 123 45 67')
                && str_contains($text, 'Rəşid Behbudov 10')
                && str_contains($text, 'Zəng etməyin, sürpriz')
                && str_contains($text, '/admin/orders/' . $order->id . '/edit');
        });
    }

    public function test_nothing_is_sent_while_the_bot_is_not_set_up(): void
    {
        Http::fake();
        Telegram::saveToken('');

        $this->order();

        Http::assertNothingSent();
    }

    public function test_a_failing_telegram_does_not_break_the_order(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'chat not found'], 400)]);

        $order = $this->order();

        $this->assertNotNull($order->id, 'the order is placed even when Telegram refuses');
    }

    public function test_the_owner_sets_the_bot_up_from_the_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // The chat is found from whoever wrote to the bot last.
        Http::fake(['*/getUpdates' => Http::response(['result' => [
            ['message' => ['chat' => ['id' => 777111, 'type' => 'private']]],
        ]])]);

        Livewire::test(SiteSettings::class)
            ->fillForm(['telegram_token' => '999:XYZ', 'telegram_chat' => ''])
            ->callAction('findChat')
            ->assertHasNoActionErrors()
            ->assertSet('data.telegram_chat', '777111');

        Livewire::test(SiteSettings::class)
            ->fillForm(['telegram_token' => '999:XYZ', 'telegram_chat' => '777111'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('999:XYZ', Telegram::token());
        $this->assertSame('777111', Telegram::chat());
        $this->assertNotSame('999:XYZ', Setting::get(Setting::TELEGRAM_TOKEN), 'the token is kept encrypted');
    }
}
