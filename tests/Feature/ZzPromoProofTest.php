<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use App\Support\Accounting;
use App\Support\OrderEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZzPromoProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Give the door method a price, so the delivery is visible in the sums.
        DeliveryMethod::where('type', 'door')->update(['price' => 5, 'is_active' => true]);
    }

    private function box(float $price = 20): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** @param array<int, Product> $boxes */
    private function order(User $user, array $boxes, ?string $code = null): Order
    {
        foreach ($boxes as $b) {
            $this->actingAs($user)->post(route('cart.add'), ['product_id' => $b->id])->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->post(route('checkout.store'), array_filter([
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => $code,
        ]))->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    private function books(Order $o): string
    {
        $report = Accounting::report();
        $row = collect($report['rows'])->first(fn ($r) => $r['order']->id === $o->id);

        return 'goods=' . $row['goods'] . ' revenue=' . $row['revenue']
            . ' | whole report: revenue=' . $report['revenue'] . ' net=' . $report['net'] . ' cash=' . $report['cash'];
    }

    public function test_A_books_after_a_line_is_deleted(): void
    {
        PromoCode::create(['code' => 'YARI', 'percent' => 50, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [$this->box(20)], 'YARI');
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $order = $order->fresh()->load('items');

        fwrite(STDERR, "\n[A] 20 goods, 50% code, 5 delivery -> discount={$order->discount} total={$order->total()} CHARGED\n");
        fwrite(STDERR, "[A] books while whole: " . $this->books($order) . "\n");

        $line = $order->items()->first();
        $adj = OrderEditor::change($order, 'test', fn () => $line->delete());
        $order = $order->fresh()->load('items', 'adjustments');
        fwrite(STDERR, "[A] line deleted: itemsTotal={$order->itemsTotal()} discount(stored)={$order->discount}"
            . " discountOff={$order->discountOff()} total={$order->total()} refund=" . ($adj ? $adj->amount : 'none') . "\n");
        fwrite(STDERR, "[A] books now:   " . $this->books($order) . "\n");

        if ($adj) {
            OrderEditor::settle($adj);
        }
        $order = $order->fresh()->load('items', 'adjustments');
        fwrite(STDERR, "[A] refund handed over: " . $this->books($order) . "\n");
        fwrite(STDERR, "[A] TRUTH: 15 in, 10 back => 5 in hand, revenue should be 5 (the delivery)\n");

        $this->assertTrue(true);
    }

    public function test_B_hundred_per_cent_then_the_line_goes(): void
    {
        PromoCode::create(['code' => 'PULSUZ', 'percent' => 100, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [$this->box(20)], 'PULSUZ');
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $order = $order->fresh()->load('items');
        fwrite(STDERR, "\n[B] 100% code: discount={$order->discount} total={$order->total()} CHARGED (delivery only)\n");
        fwrite(STDERR, "[B] books while whole: " . $this->books($order) . "\n");

        $line = $order->items()->first();
        $adj = OrderEditor::change($order, 'test', fn () => $line->delete());
        $order = $order->fresh()->load('items', 'adjustments');
        fwrite(STDERR, "[B] line deleted: total={$order->total()} adjustment=" . ($adj ? $adj->amount : 'NONE — nothing moved')
            . " paidSoFar={$order->paidSoFar()}\n");
        fwrite(STDERR, "[B] books now: " . $this->books($order) . " (5 really came in and nothing went out)\n");

        $this->assertTrue(true);
    }

    public function test_C_one_box_of_two_taken_off(): void
    {
        PromoCode::create(['code' => 'YARI', 'percent' => 50, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [$this->box(20), $this->box(20)], 'YARI');
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $order = $order->fresh()->load('items');
        fwrite(STDERR, "\n[C] two 20 boxes, 50% code, 5 delivery: itemsTotal={$order->itemsTotal()}"
            . " discount={$order->discount} total={$order->total()} CHARGED\n");

        $line = $order->items()->first();
        $adj = OrderEditor::change($order, 'test', fn () => $line->delete());
        $order = $order->fresh()->load('items', 'adjustments');
        fwrite(STDERR, "[C] one box left: itemsTotal={$order->itemsTotal()} discount={$order->discount}"
            . " discountOff={$order->discountOff()} total={$order->total()}\n");
        fwrite(STDERR, "[C] refund written: " . ($adj ? $adj->amount : '0')
            . " -> he has paid " . (25 - ($adj ? $adj->amount : 0)) . " for one 20 box at 50% + 5 delivery (should be 15)\n");
        fwrite(STDERR, "[C] effective discount now " . round($order->discountOff() / max($order->itemsTotal(), 0.01) * 100, 2)
            . "% while the order still says promo_percent={$order->promo_percent}\n");
        fwrite(STDERR, "[C] books: " . $this->books($order) . "\n");

        $this->assertTrue(true);
    }

    public function test_D_rounding(): void
    {
        PromoCode::create(['code' => 'T33', 'percent' => 33, 'is_active' => true]);
        $p = PromoCode::byCode('T33');
        fwrite(STDERR, "\n[D] 33% of 10.01 = " . $p->discountOn(10.01) . "; really "
            . round($p->discountOn(10.01) / 10.01 * 100, 4) . "% off; charged " . round(10.01 - $p->discountOn(10.01), 2) . "\n");
        fwrite(STDERR, "[D] 33% of 0.01 = " . $p->discountOn(0.01) . "; 33% of 0 = " . $p->discountOn(0.0) . "\n");

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $this->box(0)->id]);
        $r = $this->actingAs($user)->postJson(route('checkout.promo'), ['code' => 'T33']);
        fwrite(STDERR, "[D] /promokod on a cart worth nothing: " . $r->getContent() . "\n");

        $this->assertTrue(true);
    }

    public function test_E_phone_till_unpaid_figure(): void
    {
        PromoCode::create(['code' => 'YARI', 'percent' => 50, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [$this->box(20)], 'YARI');
        $order->forceFill(['status' => 'awaiting_payment'])->save();
        $order = $order->fresh()->load('items');
        $sum = Order::query()->with('items')->whereIn('status', ['awaiting_payment', 'payment_check'])->get()
            ->sum(fn (Order $o) => $o->itemsTotal() + (float) ($o->delivery_price ?? 0) + (float) ($o->rush_fee ?? 0));
        fwrite(STDERR, "\n[E] the payment page asks {$order->total()}; the phone till's \"not arrived yet\" line says {$sum}\n");

        $this->assertTrue(true);
    }

    public function test_F_a_one_use_code_used_twice_by_the_same_customer(): void
    {
        PromoCode::create(['code' => 'BIRDEFE', 'percent' => 50, 'max_uses' => 1, 'is_active' => true]);
        $user = User::factory()->create();

        // He places one order with the code and does not pay for it yet...
        $a = $this->order($user, [$this->box(20)], 'BIRDEFE');
        $a->forceFill(['status' => 'awaiting_payment'])->save();
        // ...and places a second with the same one-use code.
        $b = $this->order($user, [$this->box(20)], 'BIRDEFE');
        $b->forceFill(['status' => 'awaiting_payment'])->save();

        fwrite(STDERR, "\n[F] max_uses=1 — order #{$a->id} discount={$a->discount}, order #{$b->id} discount={$b->discount},"
            . " used_count=" . PromoCode::byCode('BIRDEFE')->used_count . "\n");

        // Both are then paid.
        $a->fresh()->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $b->fresh()->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        $p = PromoCode::byCode('BIRDEFE');
        fwrite(STDERR, "[F] both paid: used_count={$p->used_count} of max_uses={$p->max_uses}\n");

        $this->assertTrue(true);
    }

    public function test_G_a_pending_order_never_spends_the_code(): void
    {
        // No payment account and no ePoint: checkout puts the order straight
        // to 'pending' and the owner rings the customer.
        PromoCode::create(['code' => 'BIRDEFE', 'percent' => 50, 'max_uses' => 1, 'is_active' => true]);
        $user = User::factory()->create();
        $a = $this->order($user, [$this->box(20)], 'BIRDEFE');
        fwrite(STDERR, "\n[G] order status={$a->status} discount={$a->discount} payment_confirmed_at="
            . var_export($a->payment_confirmed_at, true) . "\n");
        // The owner walks it to completed by hand, the usual way.
        $a->fresh()->forceFill(['status' => 'completed'])->save();
        $p = PromoCode::byCode('BIRDEFE');
        fwrite(STDERR, "[G] after the order is completed and in the books: used_count={$p->used_count} state={$p->stateLabel()}\n");
        $b = $this->order($user, [$this->box(20)], 'BIRDEFE');
        fwrite(STDERR, "[G] a second order with the same one-use code: discount={$b->discount}\n");

        $this->assertTrue(true);
    }

    public function test_H_cancelled_and_refunded_orders_keep_the_use(): void
    {
        PromoCode::create(['code' => 'BIRDEFE', 'percent' => 50, 'max_uses' => 1, 'is_active' => true]);
        $order = $this->order(User::factory()->create(), [$this->box(20)], 'BIRDEFE');
        $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now()])->save();
        fwrite(STDERR, "\n[H] paid: used_count=" . PromoCode::byCode('BIRDEFE')->used_count . "\n");
        $order->fresh()->forceFill(['status' => 'refunded'])->save();
        $p = PromoCode::byCode('BIRDEFE');
        fwrite(STDERR, "[H] after the money was given back: used_count={$p->used_count} state={$p->stateLabel()}\n");

        $this->assertTrue(true);
    }
}
