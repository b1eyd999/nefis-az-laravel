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

    /**
     * What the owner wants once the card works: the transfer accounts switched
     * off in the admin, and the page down to one button.
     */
    public function test_with_the_accounts_switched_off_the_card_is_the_whole_page(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);
        PaymentAccount::query()->update(['is_active' => false]);

        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()
            ->assertSee('Kartla ödə', false)
            // Nothing about transfers is left to confuse him.
            ->assertDontSee('Çeki göndər', false)
            ->assertDontSee('Ödəniş üsulu', false)
            ->assertDontSee('Ödəniş hesabları hazırda əlçatan deyil', false);
    }

    /**
     * The order's status used to be decided by whether a transfer account was
     * set up, because that was once the only way to pay. With the accounts
     * switched off and the card on, every order went through as "pending" and
     * the customer was never shown a way to pay at all.
     */
    public function test_the_card_alone_is_enough_to_send_a_customer_to_pay(): void
    {
        $this->switchOn();
        PaymentAccount::query()->delete();      // nothing but the card is left

        $user = User::factory()->create();
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.pay', Order::latest('id')->first()));

        $order = Order::latest('id')->firstOrFail();
        $this->assertSame('awaiting_payment', $order->status);
        $this->assertTrue($order->awaitsPayment());

        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()->assertSee('Kartla ödə', false);
    }

    /** With no card and no accounts the shop still takes the order by hand. */
    public function test_with_no_way_to_pay_the_order_goes_through_as_before(): void
    {
        $user = User::factory()->create();
        $order = $this->order($user);
        PaymentAccount::query()->delete();

        $this->assertFalse(Epoint::enabled());
        $this->assertSame('awaiting_payment', $order->status, 'an account existed when it was placed');

        // A second order, now that nothing is left.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => Product::first()->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $this->assertSame('pending', Order::latest('id')->first()->status);
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

    /**
     * The owner cannot see what he pasted into a password field, and the
     * browser likes to paste its own saved password there, so the admin has a
     * button that asks the gateway itself whether the keys are the right ones.
     */
    public function test_the_admin_can_ask_the_gateway_whether_the_keys_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->switchOn();

        Livewire::test(SiteSettings::class)->callAction('epointCheck')->assertHasNoActionErrors();

        Http::assertSent(function ($request) {
            $body = Epoint::decode($request['data']);

            return $request->url() === Epoint::API
                && Epoint::verify($request['data'], $request['signature'])
                && $body['amount'] === '1.00'
                && str_starts_with((string) $body['order_id'], 'yoxlama-');
        });

        // Nothing was ordered and nothing was paid.
        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_the_check_says_so_when_the_keys_are_missing(): void
    {
        Http::preventStrayRequests();       // nothing may be asked of the gateway

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame('error', Epoint::check()['status']);
    }

    /**
     * The bank sends the customer back before it tells our server anything, so
     * for a minute the shop still believes nothing was paid. It must not offer
     * to take his money again in that minute.
     */
    public function test_while_a_payment_is_on_its_way_the_shop_does_not_ask_again(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);

        $this->actingAs($user)->post(route('orders.pay.card', $order))->assertRedirect('https://epoint.az/pay/abc');
        $order->refresh();

        $this->assertNotNull($order->payment_started_at);
        $this->assertSame(number_format($order->total(), 2, '.', ''), number_format($order->payment_asked_for, 2, '.', ''));
        $this->assertTrue($order->paymentInFlight());

        // No button, no second page at the bank, and no bar carrying him back.
        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()
            ->assertSee('Ödəniş yoxlanılır', false)
            // "Kartla ödəyin" in the steps starts the same way and the class
            // is in the page's own styles, so the form's address is the tell.
            ->assertDontSee('action="' . route('orders.pay.card', $order) . '"', false);
        $this->actingAs($user)->post(route('orders.pay.card', $order))
            ->assertRedirect(route('orders.pay', $order));
        $this->assertNull(Order::unpaidFor($user));

        // Half an hour later he may try again — he never did pay.
        $this->travel(31)->minutes();
        $this->assertFalse($order->fresh()->paymentInFlight());
        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()->assertSee('action="' . route('orders.pay.card', $order) . '"', false);
    }

    /** A refused card is over at once: he may try again without waiting. */
    public function test_a_refusal_ends_the_wait(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);
        $this->actingAs($user)->post(route('orders.pay.card', $order));

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'failed', 'code' => '116',
            'transaction' => 'tx-5', 'amount' => $order->total()])->assertOk();

        $this->assertFalse($order->fresh()->paymentInFlight());
        $this->actingAs($user)->get(route('orders.pay', $order))->assertOk()->assertSee('action="' . route('orders.pay.card', $order) . '"', false);
    }

    /** Two real charges on one order: the owner has to hear about it. */
    public function test_a_second_charge_is_not_swallowed(): void
    {
        $this->switchOn();
        $order = $this->order();
        $body = ['order_id' => Epoint::reference($order), 'status' => 'success', 'amount' => $order->total()];

        $this->answer($body + ['transaction' => 'tx-first'])->assertOk();
        $this->assertSame('tx-first', $order->fresh()->epoint_transaction);

        \Illuminate\Support\Facades\Log::shouldReceive('error')->once()
            ->withArgs(fn ($m, $c) => str_contains($m, 'second payment') && $c['first'] === 'tx-first' && $c['second'] === 'tx-second');
        \Illuminate\Support\Facades\Log::shouldReceive('info')->zeroOrMoreTimes();
        \Illuminate\Support\Facades\Log::shouldReceive('warning')->zeroOrMoreTimes();

        $this->answer($body + ['transaction' => 'tx-second'])->assertOk();

        // The id that actually confirmed the order is the one kept.
        $this->assertSame('tx-first', $order->fresh()->epoint_transaction);
    }

    /** The gateway's answer is checked against what was asked of it. */
    public function test_an_order_edited_mid_payment_still_settles(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);
        $asked = $order->total();

        $this->actingAs($user)->post(route('orders.pay.card', $order));

        // The owner adds delivery while the customer is at the bank.
        $order->forceFill(['delivery_price' => ($order->delivery_price ?? 0) + 5])->save();

        $this->answer(['order_id' => Epoint::reference($order), 'status' => 'success',
            'transaction' => 'tx-ok', 'amount' => $asked])->assertOk();

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /** The bank sends him back to an address with no language in it; the order remembers his. */
    public function test_back_from_the_bank_he_reads_his_own_language(): void
    {
        $this->switchOn();
        $user = User::factory()->create();
        $order = $this->order($user);
        $order->forceFill(['locale' => 'ru'])->save();

        $this->actingAs($user)->get(route('epoint.done', $order))
            ->assertRedirect(route('ru.orders.index'))
            ->assertSessionHas('status', 'Платёж принят. Заказ будет подтверждён, как только придёт подтверждение от банка.');
        $this->actingAs($user)->get(route('epoint.failed', $order))
            ->assertRedirect(route('ru.orders.pay', $order))
            ->assertSessionHas('error', 'Платёж не прошёл. Можно попробовать снова.');
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
