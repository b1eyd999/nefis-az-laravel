<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Material;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Epoint;
use App\Support\OrderEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A customer rings up after paying: add a bar, drop the live photo.
 *
 * The order's own payment is finished and must stay finished — the gateway's
 * handler refuses outright to record a second payment on an order that
 * already has one. So a change settles beside the order, on a row of its own.
 */
class OrderChangeTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'qwertyuiopasdfghjklzxcvbnm123456';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['epoint.az/*' => Http::response([
            'status' => 'success', 'transaction' => 'tx-extra', 'redirect_url' => 'https://epoint.az/pay/extra',
        ])]);
        Setting::put(Setting::EPOINT_PUBLIC_KEY, 'i000000001');
        Epoint::savePrivateKey(self::KEY);
        Setting::put(Setting::EPOINT_ENABLED, true);
    }

    private function paidOrder(?User $user = null): Order
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

        $order = Order::latest('id')->firstOrFail();
        $order->forceFill(['status' => 'confirmed', 'payment_method' => 'card', 'epoint_transaction' => 'tx-first',
            'payment_asked_for' => $order->total(), 'payment_confirmed_at' => now()])->save();

        return $order->fresh();
    }

    /** The gateway's own word, signed the way it signs it. */
    private function answer(array $body): TestResponse
    {
        $data = base64_encode((string) json_encode($body));

        return $this->post(route('epoint.result'), [
            'data' => $data,
            'signature' => base64_encode(sha1(self::KEY . $data . self::KEY, true)),
        ]);
    }

    public function test_adding_to_a_paid_order_writes_down_what_is_owed(): void
    {
        $order = $this->paidOrder();
        $was = $order->total();

        $money = OrderEditor::change($order, 'Əlavə şokolad', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));

        $this->assertNotNull($money);
        $this->assertSame(OrderAdjustment::CHARGE, $money->kind);
        $this->assertSame(6.5, round($money->amount, 2));
        $this->assertSame('Əlavə şokolad', $money->reason);

        $order = $order->fresh();
        $this->assertSame(round($was + 6.5, 2), round($order->total(), 2), 'the order is worth more now');
        $this->assertSame(round($was, 2), $order->paidSoFar(), 'but he has still only paid what he paid');
        $this->assertSame(6.5, $order->outstanding());
        $this->assertSame(0.0, $order->owedBack());
    }

    public function test_dropping_the_live_photo_puts_the_money_on_the_shops_side(): void
    {
        $order = $this->paidOrder();
        $line = $order->items()->first();
        $line->forceFill(['ar_price' => 5])->save();
        // He paid for the live photo too.
        $order->forceFill(['payment_asked_for' => $order->fresh()->load('items')->total()])->save();
        $was = $order->fresh()->load('items')->total();

        $money = OrderEditor::change($order, 'Canlandırmadan imtina etdi',
            fn () => $line->update(['ar_price' => 0]));

        $this->assertNotNull($money);
        $this->assertSame(OrderAdjustment::REFUND, $money->kind);
        $this->assertSame(5.0, round($money->amount, 2));

        $order = $order->fresh();
        $this->assertSame(round($was - 5, 2), round($order->total(), 2));
        $this->assertSame(round($was, 2), $order->paidSoFar(), 'he paid the bigger figure and is owed the difference');
        $this->assertSame(5.0, $order->owedBack());
    }

    public function test_an_order_nobody_has_paid_for_yet_needs_no_difference(): void
    {
        $order = $this->paidOrder();
        $order->forceFill(['status' => 'awaiting_payment', 'payment_confirmed_at' => null])->save();

        $money = OrderEditor::change($order->fresh(), 'Əlavə', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));

        $this->assertNull($money, 'his payment page simply asks for the new total');
        $this->assertSame(0.0, $order->fresh()->outstanding());
    }

    public function test_a_change_that_costs_nothing_writes_nothing(): void
    {
        $order = $this->paidOrder();

        $money = OrderEditor::change($order, 'Ad düzəldildi',
            fn () => $order->items()->first()->update(['product_name' => 'Başqa ad']));

        $this->assertNull($money);
        $this->assertFalse($order->fresh()->hasOpenAdjustments());
    }

    public function test_the_materials_follow_the_boxes_both_ways(): void
    {
        $paper = Material::create(['name' => 'Karton', 'unit' => 'ədəd', 'per_box' => 2, 'stock' => 100,
            'is_active' => true, 'last_unit_cost' => 0.5]);
        $order = $this->paidOrder();
        $before = Material::find($paper->id)->stock;
        $costBefore = round((float) $order->fresh()->materials_cost, 2);

        // A second box goes on the order.
        OrderEditor::change($order, 'Daha bir qutu', fn () => $order->items()->create([
            'product_id' => $order->items()->first()->product_id, 'product_name' => 'Test', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 20,
        ]));

        $this->assertSame(round($before - 2, 3), round(Material::find($paper->id)->stock, 3),
            'two more sheets left the shelf');

        // And comes off again.
        $extra = $order->items()->latest('id')->first();
        OrderEditor::change($order, 'Geri götürüldü', fn () => $extra->delete());

        $this->assertSame(round($before, 3), round(Material::find($paper->id)->stock, 3),
            'and came back when the line went');
        // One movement out and one back for this material — not a fresh set
        // for the whole order each time, which is what consume() would write.
        $this->assertSame(1, StockMovement::where('order_id', $order->id)
            ->where('material_id', $paper->id)->where('type', StockMovement::RETURN)->count());
        $this->assertSame(2, StockMovement::where('order_id', $order->id)
            ->where('material_id', $paper->id)->where('type', StockMovement::USAGE)->count());

        // And what the order cost in materials is back where it started —
        // the figure follows the ledger rather than drifting from it.
        $this->assertSame($costBefore, round((float) $order->fresh()->materials_cost, 2));
    }

    public function test_the_customer_sees_only_his_own_difference_and_only_while_it_is_open(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user);
        $money = OrderEditor::change($order, 'Əlavə şokolad', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));

        $this->actingAs($user)->get(route('orders.extra.show', [$order, $money]))
            ->assertOk()->assertSee('Əlavə şokolad')->assertSee('6.50');

        $this->actingAs(User::factory()->create())
            ->get(route('orders.extra.show', [$order, $money]))->assertForbidden();

        OrderEditor::settle($money);
        $this->actingAs($user)->get(route('orders.extra.show', [$order, $money]))->assertNotFound();
    }

    public function test_the_bank_confirms_the_difference_without_touching_the_first_payment(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user);
        $firstPaidAt = $order->payment_confirmed_at;

        $money = OrderEditor::change($order, 'Əlavə şokolad', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));

        $this->actingAs($user)->post(route('orders.extra.card', [$order, $money]))
            ->assertRedirect('https://epoint.az/pay/extra');

        $money->refresh();
        $this->assertNotNull($money->epoint_ref);
        $this->assertSame(6.5, round($money->payment_asked_for, 2));
        $this->assertTrue($money->paymentInFlight());

        $this->answer(['status' => 'success', 'amount' => '6.50', 'transaction' => 'tx-extra',
            'order_id' => $money->epoint_ref])->assertOk();

        $money->refresh();
        $this->assertSame(OrderAdjustment::PAID, $money->status);
        $this->assertNotNull($money->payment_confirmed_at);

        $order = $order->fresh();
        $this->assertSame(0.0, $order->outstanding(), 'nothing is owed any more');
        $this->assertSame('tx-first', $order->epoint_transaction, 'the first payment is untouched');
        $this->assertSame($firstPaidAt->toDateTimeString(), $order->payment_confirmed_at->toDateTimeString());
        $this->assertSame('confirmed', $order->status);
    }

    public function test_the_bank_saying_it_twice_changes_nothing(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user);
        $money = OrderEditor::change($order, 'Əlavə', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));
        $this->actingAs($user)->post(route('orders.extra.card', [$order, $money]));
        $ref = $money->fresh()->epoint_ref;

        $this->answer(['status' => 'success', 'amount' => '6.50', 'transaction' => 'tx-extra', 'order_id' => $ref])->assertOk();
        $when = $money->fresh()->payment_confirmed_at;
        $this->answer(['status' => 'success', 'amount' => '6.50', 'transaction' => 'tx-extra', 'order_id' => $ref])->assertOk();

        $this->assertSame($when->toDateTimeString(), $money->fresh()->payment_confirmed_at->toDateTimeString());
        $this->assertSame(1, OrderAdjustment::where('order_id', $order->id)->count());
    }

    public function test_a_different_amount_from_the_bank_is_refused(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user);
        $money = OrderEditor::change($order, 'Əlavə', fn () => $order->items()->create([
            'product_id' => null, 'product_name' => 'Şokolad', 'quantity' => 1,
            'customer_photos' => [], 'custom_texts' => [], 'price' => 6.5,
        ]));
        $this->actingAs($user)->post(route('orders.extra.card', [$order, $money]));

        $this->answer(['status' => 'success', 'amount' => '1.00', 'transaction' => 'tx-extra',
            'order_id' => $money->fresh()->epoint_ref])->assertOk()->assertSee('amount mismatch');

        $this->assertSame(OrderAdjustment::WAITING, $money->fresh()->status);
        $this->assertSame(6.5, $order->fresh()->outstanding());
    }

    public function test_the_order_itself_still_refuses_a_second_payment(): void
    {
        $order = $this->paidOrder();

        // The guard that was there before is exactly as it was: a payment
        // addressed to the ORDER, on an order already paid, is not recorded.
        $this->answer(['status' => 'success', 'amount' => (string) $order->payment_asked_for,
            'transaction' => 'tx-second', 'order_id' => $order->id . '-251004120000'])->assertOk();

        $this->assertSame('tx-first', $order->fresh()->epoint_transaction);
    }

    public function test_money_owed_back_is_settled_by_hand_not_by_a_link(): void
    {
        $user = User::factory()->create();
        $order = $this->paidOrder($user);
        $line = $order->items()->first();
        $line->forceFill(['ar_price' => 5])->save();
        $order->forceFill(['payment_asked_for' => $order->fresh()->load('items')->total()])->save();

        $money = OrderEditor::change($order, 'Canlandırmadan imtina', fn () => $line->update(['ar_price' => 0]));

        // There is no refund call in the gateway, so the customer has no page.
        $this->actingAs($user)->get(route('orders.extra.show', [$order, $money]))->assertNotFound();
        $this->actingAs($user)->post(route('orders.extra.card', [$order, $money]))->assertNotFound();

        OrderEditor::settle($money, 'cabinet');
        $this->assertSame(0.0, $order->fresh()->owedBack());
    }
}
