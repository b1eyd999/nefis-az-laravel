<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Accounting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner makes a code, the customer types it at the checkout.
 *
 * The discount comes off the goods only — the delivery and the rush fee are
 * what the shop pays the courier and the workshop, and a code eating into
 * those would take the money out of the owner's pocket rather than his
 * margin. What a code was worth is frozen on the order, so editing it
 * afterwards never changes what somebody already paid.
 */
class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    private function box(float $price = 20): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function order(User $user, Product $box, ?string $code = null): Order
    {
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), array_filter([
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => $code,
        ]))->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    public function test_the_owner_makes_a_code_and_it_takes_its_percent_off_the_goods(): void
    {
        $promo = PromoCode::create(['code' => 'yaz10', 'percent' => 10]);
        $this->assertSame('YAZ10', $promo->code, 'however it was typed');

        $box = $this->box(20);
        $order = $this->order(User::factory()->create(), $box, 'yaz10');

        $this->assertSame('YAZ10', $order->promo_code);
        $this->assertSame(10.0, $order->promo_percent);
        $this->assertSame(2.0, round($order->discount, 2));
        $this->assertSame(20.0, round($order->itemsTotal(), 2));
        // The delivery is untouched by the code.
        $this->assertSame(round(20 - 2 + (float) $order->delivery_price, 2), round($order->total(), 2));
        // Not spent yet: the order is only written down, nobody has paid.
        $this->assertSame(0, $promo->fresh()->used_count);
    }

    public function test_a_generated_code_is_unique_and_easy_to_read(): void
    {
        $seen = [];
        for ($i = 0; $i < 30; $i++) {
            $code = PromoCode::make();
            $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $code);
            $this->assertStringNotContainsString('O', $code, 'nothing that reads as a zero');
            $this->assertStringNotContainsString('I', $code, 'nothing that reads as a one');
            $seen[] = $code;
        }
        $this->assertSame($seen, array_unique($seen));
    }

    public function test_a_code_that_is_off_expired_or_used_up_takes_nothing(): void
    {
        $box = $this->box(20);

        $cases = [
            'off' => ['code' => 'OFF1', 'percent' => 50, 'is_active' => false],
            'expired' => ['code' => 'OLD1', 'percent' => 50, 'ends_at' => now()->subDay()],
            'early' => ['code' => 'SOON1', 'percent' => 50, 'starts_at' => now()->addDay()],
            'spent' => ['code' => 'SPENT1', 'percent' => 50, 'max_uses' => 1, 'used_count' => 1],
            'small' => ['code' => 'BIG1', 'percent' => 50, 'min_total' => 100],
        ];

        foreach ($cases as $why => $fields) {
            PromoCode::create($fields);
            $order = $this->order(User::factory()->create(), $box, $fields['code']);

            $this->assertSame(0.0, round((float) $order->discount, 2), "a {$why} code must take nothing off");
            $this->assertNull($order->promo_code);
        }
    }

    public function test_the_page_is_told_why_a_code_will_not_work(): void
    {
        $box = $this->box(20);
        $user = User::factory()->create();
        PromoCode::create(['code' => 'OLD1', 'percent' => 50, 'ends_at' => now()->subDay()]);
        PromoCode::create(['code' => 'YAZ10', 'percent' => 10]);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);

        $this->actingAs($user)->postJson(route('checkout.promo'), ['code' => 'yoxdur'])
            ->assertOk()->assertJson(['ok' => false]);
        $this->actingAs($user)->postJson(route('checkout.promo'), ['code' => 'OLD1'])
            ->assertOk()->assertJson(['ok' => false]);
        $this->actingAs($user)->postJson(route('checkout.promo'), ['code' => 'yaz10'])
            ->assertOk()->assertJson(['ok' => true, 'code' => 'YAZ10', 'discount' => 2]);
    }

    public function test_editing_the_code_afterwards_does_not_move_a_placed_order(): void
    {
        $promo = PromoCode::create(['code' => 'YAZ10', 'percent' => 10]);
        $box = $this->box(20);
        $order = $this->order(User::factory()->create(), $box, 'YAZ10');
        $was = $order->total();

        $promo->update(['percent' => 90]);
        $promo->delete();

        $this->assertSame(round($was, 2), round($order->fresh()->total(), 2));
        $this->assertSame(2.0, round($order->fresh()->discount, 2));
    }

    public function test_the_books_count_what_was_charged_not_the_list_price(): void
    {
        PromoCode::create(['code' => 'YAZ10', 'percent' => 10]);
        $box = $this->box(20);
        $order = $this->order(User::factory()->create(), $box, 'YAZ10');
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();

        $report = Accounting::report();
        $row = collect($report['rows'] ?? [])->first(fn ($r) => $r['order']->id === $order->id);

        $this->assertNotNull($row, 'the order is in the books');
        $this->assertSame(18.0, round((float) $row['goods'], 2), 'twenty manats less the two it took off');
    }

    public function test_a_hundred_per_cent_makes_the_goods_free_but_not_the_delivery(): void
    {
        PromoCode::create(['code' => 'PULSUZ', 'percent' => 100]);
        $box = $this->box(20);
        $order = $this->order(User::factory()->create(), $box, 'PULSUZ');

        $this->assertSame(20.0, round((float) $order->discount, 2));
        $this->assertSame(round((float) $order->delivery_price, 2), round($order->total(), 2));
    }

    public function test_only_the_owner_reaches_the_codes(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/promo-codes')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/promo-codes')->assertOk();
    }

    public function test_a_use_is_spent_when_the_money_lands_not_when_the_order_is_written(): void
    {
        $promo = PromoCode::create(['code' => 'BIR', 'percent' => 10, 'max_uses' => 1]);
        $box = $this->box(20);

        // Someone places an order and walks away from the payment page.
        // Nothing is spent yet — the count only moves when the money lands —
        // but the single use is spoken for, and this test used to assert the
        // opposite: that the code was still free for the next customer. It
        // was not. Both of them could pay, and each payment found the cap
        // still unreached.
        $first = $this->order(User::factory()->create(), $box, 'BIR');
        $this->assertSame(0, $promo->fresh()->used_count);
        $this->assertSame(1, $promo->fresh()->claimed(), 'one unpaid order is holding it');
        $this->assertFalse($promo->fresh()->isUsable());

        $second = $this->order(User::factory()->create(), $box, 'BIR');
        $this->assertNull($second->promo_code, 'the second customer is not given it');
        $this->assertSame(0.0, round((float) $second->discount, 2));

        // The first one pays, and the claim becomes a use.
        $first->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $this->assertSame(1, $promo->fresh()->used_count);
        $this->assertSame(1, $promo->fresh()->claimed(), 'spent once, held by nobody');
        $this->assertFalse($promo->fresh()->isUsable());

        // And confirming the same order again does not spend it twice.
        $first->forceFill(['status' => 'ready'])->save();
        $this->assertSame(1, $promo->fresh()->used_count);

        // Giving the order up hands the use back.
        $third = PromoCode::create(['code' => 'IKI', 'percent' => 10, 'max_uses' => 1]);
        $dropped = $this->order(User::factory()->create(), $box, 'IKI');
        $this->assertFalse($third->fresh()->isUsable());
        $dropped->forceFill(['status' => 'cancelled'])->save();
        $this->assertTrue($third->fresh()->isUsable(), 'a cancelled order releases its claim');
    }

    public function test_the_discount_never_outlives_the_goods_it_came_off(): void
    {
        PromoCode::create(['code' => 'PULSUZ', 'percent' => 100]);
        $box = $this->box(20);
        $user = User::factory()->create();

        // Two boxes, all of it taken off.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => 'PULSUZ',
        ])->assertSessionHasNoErrors();
        $order = Order::latest('id')->firstOrFail();
        $this->assertSame(40.0, round((float) $order->discount, 2));

        // The owner then takes one of the lines off. The frozen discount is
        // bigger than what is left, and the order must not go negative.
        $order->items()->latest('id')->first()->delete();
        $order = $order->fresh()->load('items');

        $this->assertSame(20.0, round($order->itemsTotal(), 2));
        $this->assertSame(20.0, round($order->discountOff(), 2), 'only as much as there is');
        $this->assertSame(round((float) $order->delivery_price, 2), round($order->total(), 2));
        $this->assertGreaterThanOrEqual(0, $order->total());
        // What the code was worth on the day is still on the order.
        $this->assertSame(40.0, round((float) $order->discount, 2));
    }

    public function test_an_array_where_a_code_was_expected_is_refused_not_a_crash(): void
    {
        $box = $this->box(20);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => ['YAZ10'],
        ])->assertSessionHasErrors('promo_code');

        $this->assertSame(0, Order::count());
    }

    public function test_a_code_that_takes_nothing_off_is_not_called_applied(): void
    {
        PromoCode::create(['code' => 'YAZ10', 'percent' => 10]);
        $user = User::factory()->create();
        // An empty basket: nothing for the code to work on.
        $this->actingAs($user)->postJson(route('checkout.promo'), ['code' => 'YAZ10'])
            ->assertOk()->assertJson(['ok' => false]);
    }
}
