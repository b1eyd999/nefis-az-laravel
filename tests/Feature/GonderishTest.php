<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Filament\Pages\SiteSettings;
use App\Support\Courier;
use App\Support\Gonderish;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The customer's WhatsApp through Göndəriş: the order is in, the money
 * landed, the courier has left, the status moved — each said once, in the
 * language the order was placed in, and never in the way of the order itself.
 */
class GonderishTest extends TestCase
{
    use RefreshDatabase;

    private const QUEUED = ['ok' => true, 'message' => ['id' => 1, 'status' => 'queued']];

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

    private function switchOn(bool $placed = true, bool $paid = true, bool $way = false, array $statuses = []): void
    {
        Gonderish::saveKey('gnd_test_key');
        Setting::put(Setting::GONDERISH_PLACED, $placed);
        Setting::put(Setting::GONDERISH_PAID, $paid);
        Setting::put(Setting::GONDERISH_ON_THE_WAY, $way);
        Gonderish::saveStatuses($statuses);
    }

    public function test_a_placed_order_writes_to_the_customer_once(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn();

        $order = $this->placeOrder();

        /* Paid at the door, so the shop never waited for money: the order
           opens at `pending`, and that is the status the first message
           announces and the slot it is filed under. */
        $this->assertSame('pending', $order->status);

        Http::assertSent(fn (Request $r) => $r->url() === Gonderish::URL.'/messages'
            && $r->hasHeader('Authorization', 'Bearer gnd_test_key')
            && $r['to'] === '994501234567'
            && $r['ref'] === "nefis-{$order->id}-st-pending"
            && str_contains($r['text'], 'sifariş #'.$order->id));
        Http::assertSentCount(1);

        // The same moment again (a double click, a retried request) writes nothing new.
        Gonderish::placed($order);
        Http::assertSentCount(1);
    }

    public function test_confirmed_payment_writes_that_the_money_is_in(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn(placed: false);
        $order = $this->placeOrder();
        Http::assertNothingSent();

        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        Http::assertSent(fn (Request $r) => $r['ref'] === "nefis-{$order->id}-st-confirmed"
            && str_contains($r['text'], 'Ödəniş təsdiqləndi'));
    }

    /**
     * The one that pays for the whole slot scheme.
     *
     * Confirming a payment stamps `payment_confirmed_at` and moves the status
     * in a single save, so two separate hooks fire — «paid» and «the status
     * changed». Both announce the very same sentence. Keyed by the event they
     * would arrive as two WhatsApp messages a second apart; keyed by the
     * status the second is dropped.
     */
    public function test_payment_and_the_status_change_are_one_message_not_two(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn(placed: false, paid: true, statuses: ['confirmed', 'ready', 'completed']);
        $order = $this->placeOrder();

        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        Http::assertSentCount(1);
    }

    public function test_a_ticked_status_writes_and_an_unticked_one_does_not(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn(placed: false, paid: false, statuses: ['completed']);
        $order = $this->placeOrder();

        $order->forceFill(['status' => 'ready'])->save();
        Http::assertNothingSent();

        $order->forceFill(['status' => 'completed'])->save();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['ref'] === "nefis-{$order->id}-st-completed"
            && str_contains($r['text'], 'təhvil verildi'));

        // Back and forth over the same status says nothing a second time.
        $order->forceFill(['status' => 'ready'])->save();
        $order->forceFill(['status' => 'completed'])->save();
        Http::assertSentCount(1);
    }

    public function test_the_courier_leaving_carries_his_name_and_what_is_owed(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn(placed: false, paid: false, way: true);
        $order = $this->placeOrder();

        $courier = User::factory()->create(['name' => 'Rəşad', 'phone' => '0555555555', 'role' => User::COURIER]);
        $order->forceFill(['courier_id' => $courier->id])->saveQuietly();

        $this->assertNull(Courier::tellCustomer($order->fresh()));

        Http::assertSent(fn (Request $r) => $r['ref'] === "nefis-{$order->id}-way"
            && str_contains($r['text'], 'Kuryeriniz yola düşdü')
            && str_contains($r['text'], 'Rəşad'));
        Http::assertSentCount(1);
    }

    /** Switched off, the courier's message is the owner's own tap as before. */
    public function test_the_courier_message_waits_for_the_switch(): void
    {
        Http::fake(['gonderish.az/*' => Http::response(self::QUEUED, 201)]);
        $this->switchOn(placed: false, paid: false, way: false);
        $order = $this->placeOrder();

        Courier::tellCustomer($order);

        Http::assertNothingSent();
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

    /**
     * A refusal must not burn the slot.
     *
     * The lock is taken before the request so two confirmations at once
     * cannot both get through; if the request then fails, the lock has to go
     * again, or the message could never be sent for that order at all.
     */
    public function test_a_refusal_leaves_the_moment_free_to_try_again(): void
    {
        $down = true;
        Http::fake(function () use (&$down) {
            return Http::response($down ? ['ok' => false, 'error' => 'no_balance'] : self::QUEUED, $down ? 402 : 201);
        });
        $this->switchOn();

        $order = $this->placeOrder();
        Http::assertSentCount(1);

        $down = false;
        Gonderish::placed($order->fresh());

        Http::assertSentCount(2);
    }

    public function test_a_connected_number_with_no_messages_left_is_not_called_ready(): void
    {
        Http::fake(['gonderish.az/api/v1/me' => Http::response([
            'ok' => true, 'unlimited' => false, 'balance' => 0,
            'numbers' => [['id' => 1, 'phone' => '994501112233', 'connected' => true]],
        ])]);
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::check();

        $this->assertFalse($ok, 'a number that cannot send is not ready');
        $this->assertStringContainsString('balans 0', $said);
    }

    public function test_a_working_key_names_the_number_that_will_write(): void
    {
        Http::fake(['gonderish.az/api/v1/me' => Http::response([
            'ok' => true, 'unlimited' => false, 'balance' => 40,
            'numbers' => [['id' => 1, 'phone' => '994501112233', 'connected' => true]],
        ])]);
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::check();

        $this->assertTrue($ok);
        $this->assertStringContainsString('994501112233', $said);
        $this->assertStringContainsString('40', $said);
    }

    /**
     * The refusal `/me` cannot see.
     *
     * Göndəriş bars sending until the terms are accepted once in its own
     * cabinet, and that is checked on the send, not on the key — so the key
     * check says «ready» and every message is refused. Only the test message
     * finds it, and it has to say so in words the owner can act on.
     */
    public function test_the_test_message_explains_the_unaccepted_terms(): void
    {
        Http::fake(['gonderish.az/api/v1/messages' => Http::response(
            ['ok' => false, 'error' => 'terms_not_accepted', 'message' => 'Accept the sending terms'], 403)]);
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::test('050 123 45 67');

        $this->assertFalse($ok);
        $this->assertStringContainsString('şərtləri qəbul edilməyib', $said);
    }

    public function test_the_test_message_goes_to_the_number_typed(): void
    {
        Http::fake(['gonderish.az/api/v1/messages' => Http::response(self::QUEUED, 201)]);
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::test('050 123 45 67');

        $this->assertTrue($ok);
        $this->assertStringContainsString('994501234567', $said);
        Http::assertSent(fn (Request $r) => $r['to'] === '994501234567'
            && str_contains($r['text'], 'yoxlama'));
    }

    public function test_a_number_that_cannot_be_read_is_refused_before_anything_is_sent(): void
    {
        Http::fake();
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::test('salam');

        $this->assertFalse($ok);
        $this->assertStringContainsString('oxunmadı', $said);
        Http::assertNothingSent();
    }

    /** A paused queue in the cabinet is accepted, and said out loud. */
    public function test_a_paused_queue_is_reported_rather_than_passed_as_sent(): void
    {
        Http::fake(['gonderish.az/api/v1/messages' => Http::response(
            self::QUEUED + ['warning' => 'The API queue of this number is paused'], 201)]);
        Gonderish::saveKey('gnd_test_key');

        [$ok, $said] = Gonderish::test('0501234567');

        $this->assertTrue($ok);
        $this->assertStringContainsString('paused', $said);
    }

    public function test_only_the_statuses_the_shop_knows_are_kept(): void
    {
        Gonderish::saveStatuses(['ready', 'nonsense', 'completed']);

        $this->assertSame(['ready', 'completed'], Gonderish::statuses());
    }

    /**
     * The settings page itself: the switches reach the form, come back from
     * it, and survive a second save.
     *
     * There is no way to look at the admin over HTTP, and `save()` writes
     * every setting by name — a later hand looping over the form's own fields
     * would drop the ones it did not know about without a sound.
     */
    public function test_the_admin_keeps_the_switches_and_the_ticked_statuses(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->assertSee('Kuryer yola düşəndə yaz')
            ->assertSee('Status dəyişəndə yaz')
            ->set('data.gonderish_key', 'gnd_from_the_form')
            ->set('data.gonderish_on_the_way', true)
            ->set('data.gonderish_statuses', ['ready', 'completed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('gnd_from_the_form', Gonderish::key(), 'the key did not survive the form');
        $this->assertSame(['ready', 'completed'], Gonderish::statuses());

        // Opened and saved again with nothing touched, the choices stay put.
        Livewire::test(SiteSettings::class)->call('save')->assertHasNoFormErrors();

        $this->assertSame(['ready', 'completed'], Gonderish::statuses());
        $this->assertTrue(Gonderish::onTheWayOn());
        $this->assertSame('gnd_from_the_form', Gonderish::key());
    }
}
