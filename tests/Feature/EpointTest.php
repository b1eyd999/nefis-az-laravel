<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Support\Epoint;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Paying by card. The gateway is never there in a test, so its answers are
 * faked and its callbacks are signed here with the same key the site holds.
 */
class EpointTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-private-key';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake(['epoint.az/*' => Http::response([
            'status' => 'success', 'transaction' => 'tx-1', 'redirect_url' => 'https://epoint.az/pay/abc',
        ])]);
    }

    private function switchOn(): void
    {
        Setting::put(Setting::EPOINT_PUBLIC_KEY, 'i000000001');
        Epoint::savePrivateKey(self::KEY);
        Setting::put(Setting::EPOINT_ENABLED, true);
    }

    private function order(?User $user = null): Order
    {
        $user ??= User::factory()->create();
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /** What the gateway sends us when a payment is over, signed the way it signs it. */
    private function answer(array $body, ?string $key = null): TestResponse
    {
        $key ??= self::KEY;
        $data = base64_encode((string) json_encode($body));

        return $this->post(route('epoint.result'), [
            'data' => $data,
            'signature' => base64_encode(sha1($key . $data . $key, true)),
        ]);
    }

    public function test_the_button_shows_up_only_when_the_owner_switched_cards_on(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->get(route('orders.pay', $order))
            ->assertOk()->assertDontSee('Kartla ödə', false);

        $this->switchOn();

        $this->actingAs($user)->get(route('orders.pay', $order))
            ->assertOk()->assertSee('Kartla ödə', false);
    }

    public function test_asking_to_pay_sends_the_customer_to_the_gateway(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->post(route('orders.pay.card', $order))
            ->assertRedirect('https://epoint.az/pay/abc');

        $order->refresh();
        $this->assertSame(['card', 'tx-1'], [$order->payment_method, $order->epoint_transaction]);
        $this->assertSame($order->id, Epoint::orderIdFrom($order->epoint_ref));
        $this->assertNull($order->payment_confirmed_at);        // nothing is paid yet

        // What we sent the gateway: our key, the order's own total, and a signature.
        Http::assertSent(function ($request) use ($order) {
            $body = Epoint::decode($request['data']);

            return $request->url() === Epoint::API
                && Epoint::verify($request['data'], $request['signature'])
                && $body['public_key'] === 'i000000001'
                && $body['amount'] === number_format($order->total(), 2, '.', '')
                && $body['currency'] === 'AZN';
        });
    }

    public function test_nobody_can_pay_or_be_told_about_somebody_elses_order(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->actingAs(User::factory()->create())->post(route('orders.pay.card', $order))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('epoint.done', ['order' => $order->id]))->assertForbidden();
    }

    public function test_the_card_is_not_offered_while_it_is_switched_off(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->post(route('orders.pay.card', $order))->assertNotFound();

        // Switched on, but the owner has not typed the keys in yet.
        Setting::put(Setting::EPOINT_ENABLED, true);
        $this->assertFalse(Epoint::enabled());
        $this->actingAs($user)->post(route('orders.pay.card', $order))->assertNotFound();
    }

    public function test_a_signed_success_confirms_the_order(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'success', 'code' => '000',
            'transaction' => 'tx-9', 'bank_transaction' => 'b-9', 'amount' => $order->total()])->assertOk();

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->payment_confirmed_at);
        $this->assertSame('tx-9', $order->epoint_transaction);
    }

    public function test_a_body_signed_with_another_key_is_thrown_away(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->answer(['order_id' => $order->id . '-1', 'status' => 'success', 'amount' => $order->total()], 'not-our-key')
            ->assertStatus(400);

        $order->refresh();
        $this->assertNull($order->payment_confirmed_at);
        $this->assertSame('awaiting_payment', $order->status);
    }

    public function test_an_amount_that_is_not_the_orders_total_leaves_it_unpaid(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'success', 'amount' => 1])->assertOk();

        $order->refresh();
        $this->assertNull($order->payment_confirmed_at);
        $this->assertSame('awaiting_payment', $order->status);
    }

    public function test_a_refused_card_does_not_move_the_order(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'failed', 'code' => '116',
            'message' => 'Kartda vəsait yoxdur', 'transaction' => 'tx-5', 'amount' => $order->total()])->assertOk();

        $order->refresh();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertNull($order->payment_confirmed_at);
        $this->assertSame('tx-5', $order->epoint_transaction);      // the attempt is still written down
    }

    public function test_the_same_answer_twice_is_paid_once(): void
    {
        $this->switchOn();
        $order = $this->order();
        $body = ['order_id' => Epoint::reference($order), 'status' => 'success', 'amount' => $order->total()];

        $this->answer($body)->assertOk();
        $first = $order->refresh()->payment_confirmed_at;

        $this->travel(2)->minutes();
        $this->answer($body)->assertOk();

        $this->assertEquals($first, $order->refresh()->payment_confirmed_at);
    }

    public function test_an_answer_about_an_order_we_do_not_have_is_simply_noted(): void
    {
        $this->switchOn();

        $this->answer(['order_id' => '90210-260928120000', 'status' => 'success', 'amount' => 20])
            ->assertOk()->assertSee('unknown order');
    }

    public function test_the_panel_says_the_card_paid_it_and_asks_for_no_receipt(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'success',
            'transaction' => 'tx-7', 'amount' => $order->total()])->assertOk();

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertSee('Kartla ödənilib')
            ->assertSee('tx-7')
            ->assertDontSee('Çek göndərilib');
    }

    public function test_the_owner_types_the_keys_into_the_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm(['epoint_enabled' => true, 'epoint_public_key' => 'i123456789', 'epoint_private_key' => 'a-secret'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Epoint::enabled());
        $this->assertSame('i123456789', Epoint::publicKey());
        $this->assertSame('a-secret', Epoint::privateKey());

        // And the address he has to give ePoint is the one the gateway calls.
        $this->assertSame(route('epoint.result'), SiteSettings::resultUrl());
    }

    public function test_the_private_key_is_not_kept_in_the_open(): void
    {
        $this->switchOn();

        $stored = (string) Setting::where('key', Setting::EPOINT_PRIVATE_KEY)->value('value');
        $this->assertNotSame(self::KEY, $stored);
        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertSame(self::KEY, Epoint::privateKey());
    }
}
