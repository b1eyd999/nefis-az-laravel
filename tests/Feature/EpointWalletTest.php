<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Epoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google Pay and Apple Pay, through epoint's wallet widget.
 *
 * The shop does not take the money itself: it asks the gateway for a small
 * page of its own and shows it in a window. What ends the payment is still
 * the signed callback — the window only says when to stop waiting.
 */
class EpointWalletTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-private-key';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        /* One stub, reading a property, rather than a second Http::fake() per
           test: a later fake does not replace an earlier one, it queues
           behind it, and the first match is the one that answers. */
        Http::fake(['epoint.az/*' => fn () => Http::response($this->gateway)]);
    }

    /** @var array<string, mixed> what the gateway will answer */
    private array $gateway = [
        'status' => 'success',
        'widget_url' => 'https://epoint.az/api/1/token/widget/000001',
    ];

    private function gatewaySays(array $body): void
    {
        $this->gateway = $body;
    }

    private function switchOn(): void
    {
        Setting::put(Setting::EPOINT_PUBLIC_KEY, 'i000000001');
        Epoint::savePrivateKey(self::KEY);
        Setting::put(Setting::EPOINT_ENABLED, true);
        Setting::put(Setting::EPOINT_WALLET, true);
    }

    /** Card payment on, wallet switch still off — the state every shop starts in. */
    private function walletOff(): void
    {
        Setting::put(Setting::EPOINT_WALLET, false);
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

    public function test_it_hands_back_a_widget_address(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->actingAs($order->user)
            ->postJson(route('orders.pay.wallet', $order))
            ->assertOk()
            ->assertJson(['url' => 'https://epoint.az/api/1/token/widget/000001']);

        // Asked for at the widget endpoint, signed the way everything else is.
        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/api/1/token/widget')) {
                return false;
            }
            $body = json_decode((string) base64_decode($request['data']), true);

            return $request['signature'] === base64_encode(sha1(self::KEY . $request['data'] . self::KEY, true))
                && $body['public_key'] === 'i000000001'
                && isset($body['order_id'], $body['amount'], $body['description']);
        });
    }

    /**
     * Opening the window is not paying.
     *
     * It used to stamp the order the way the card page does, and when the
     * widget came up blank — which is what an account without the service
     * gets — the customer had paid nothing and could not reach the card
     * button for half an hour.
     */
    public function test_opening_the_window_does_not_lock_the_order(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->actingAs($order->user)->postJson(route('orders.pay.wallet', $order))->assertOk();

        $order->refresh();
        $this->assertNull($order->payment_started_at);
        $this->assertFalse($order->paymentInFlight(), 'the card button stays within reach');

        // The card page is still offered, and pressing again is still allowed.
        $this->actingAs($order->user)->get(route('orders.pay', $order))->assertOk()->assertSee('Kartla ödə');
        $this->actingAs($order->user)->postJson(route('orders.pay.wallet', $order))->assertOk();
    }

    /** Off until epoint turns the widget on for this merchant. */
    public function test_the_wallet_has_a_switch_of_its_own(): void
    {
        $this->switchOn();
        $this->walletOff();
        $order = $this->order();

        $this->actingAs($order->user)->postJson(route('orders.pay.wallet', $order))->assertNotFound();

        /* Not the words — the hosted card page has offered both wallets in its
           own line since long before this button existed. The button itself is
           what must be gone, and it is the only thing that carries the route. */
        $page = $this->actingAs($order->user)->get(route('orders.pay', $order))->assertOk();
        $page->assertDontSee(route('orders.pay.wallet', $order));
        $page->assertDontSee('id="pay-wallet"', false);
        $page->assertSee('Kartla ödə');
    }

    public function test_nobody_else_can_open_a_payment_for_your_order(): void
    {
        $this->switchOn();
        $order = $this->order();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson(route('orders.pay.wallet', $order))->assertForbidden();
    }

    public function test_it_is_closed_when_card_payment_is_switched_off(): void
    {
        $order = $this->order();          // keys never written

        $this->actingAs($order->user)->postJson(route('orders.pay.wallet', $order))->assertNotFound();
    }

    /** A gateway that says no leaves the order untouched and says so plainly. */
    public function test_a_refusal_does_not_strand_the_order(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->gatewaySays(['status' => 'error', 'message' => 'no']);

        $this->actingAs($order->user)
            ->postJson(route('orders.pay.wallet', $order))
            ->assertStatus(502)
            ->assertJsonStructure(['error']);

        $order->refresh();
        $this->assertNull($order->payment_started_at);
        $this->assertFalse($order->paymentInFlight());
        $this->actingAs($order->user)->get(route('orders.pay', $order))->assertOk()->assertSee('Kartla ödə');
    }

    public function test_the_button_is_on_the_payment_page(): void
    {
        $this->switchOn();
        $order = $this->order();

        $this->actingAs($order->user)
            ->get(route('orders.pay', $order))
            ->assertOk()
            ->assertSee('Google Pay / Apple Pay')
            ->assertSee(route('orders.pay.wallet', $order));
    }
}
