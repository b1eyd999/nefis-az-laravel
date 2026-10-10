<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\OrderAdjustment;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\Setting;
use App\Models\User;
use App\Support\Epoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The two faults the security review left open.
 *
 * One let a single-use code be put on several unpaid orders and every one of
 * them paid afterwards; the other credited money owed for a change to the
 * order itself whenever the customer paid on the second attempt.
 */
class PromoAndAdjustmentFixesTest extends TestCase
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
        Setting::put(Setting::EPOINT_PUBLIC_KEY, 'i000000001');
        Epoint::savePrivateKey(self::KEY);
        Setting::put(Setting::EPOINT_ENABLED, true);
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function orderWith(?string $promo, ?User $user = null): Order
    {
        $user ??= User::factory()->create();
        PaymentAccount::firstOrCreate(['type' => PaymentAccount::CARD],
            ['label' => 'Kart', 'number' => '4169738111111111']);
        $box = Product::first() ?? $this->box();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => $promo,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /** What the gateway sends when a payment is over, signed the way it signs it. */
    private function answer(array $body): TestResponse
    {
        $data = base64_encode((string) json_encode($body));

        return $this->post(route('epoint.result'), [
            'data' => $data,
            'signature' => base64_encode(sha1(self::KEY . $data . self::KEY, true)),
        ]);
    }

    /**
     * A one-use code, two unpaid orders: the second must not get it.
     *
     * It used to, because the cap was read off used_count alone and that is
     * only raised when the money lands.
     */
    public function test_an_unpaid_order_already_holds_the_only_use(): void
    {
        $this->box();
        PromoCode::create(['code' => 'TEK', 'percent' => 50, 'is_active' => true, 'max_uses' => 1, 'used_count' => 0]);

        $first = $this->orderWith('TEK');
        $this->assertNotNull($first->promo_code, 'the first order gets the code');

        $code = PromoCode::where('code', 'TEK')->first();
        $this->assertSame(0, (int) $code->used_count, 'nothing is spent until the money lands');
        $this->assertSame(1, $code->claimed(), 'but one order is holding it');
        $this->assertTrue($code->hasRunOut());
        $this->assertSame(0, PromoCode::usable()->where('code', 'TEK')->count());

        // A second customer is refused.
        $second = $this->orderWith('TEK', User::factory()->create());
        $this->assertNull($second->promo_code, 'the second order does not get the code');
    }

    /** Give the order up and the code is free again. */
    public function test_a_cancelled_order_releases_its_claim(): void
    {
        $this->box();
        PromoCode::create(['code' => 'TEK', 'percent' => 50, 'is_active' => true, 'max_uses' => 1, 'used_count' => 0]);

        $first = $this->orderWith('TEK');
        $this->assertTrue(PromoCode::where('code', 'TEK')->first()->hasRunOut());

        $first->forceFill(['status' => 'cancelled'])->save();

        $this->assertFalse(PromoCode::where('code', 'TEK')->first()->hasRunOut());
        $this->assertSame(0, PromoCode::where('code', 'TEK')->first()->claimed());
    }

    /** Paying moves the claim into the count, and does not double it. */
    public function test_paying_does_not_count_the_same_order_twice(): void
    {
        $this->box();
        PromoCode::create(['code' => 'TEK', 'percent' => 50, 'is_active' => true, 'max_uses' => 2, 'used_count' => 0]);

        $order = $this->orderWith('TEK');
        $this->assertSame(1, PromoCode::where('code', 'TEK')->first()->claimed());

        $order->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();

        $code = PromoCode::where('code', 'TEK')->first();
        $this->assertSame(1, (int) $code->used_count);
        $this->assertSame(1, $code->claimed(), 'spent once, held by nobody');
    }

    /**
     * The extra is recognised by what the reference says, not by the last
     * reference written down — the customer may have tried twice.
     */
    public function test_the_reference_says_which_extra_it_is(): void
    {
        $this->assertSame(7, Epoint::adjustmentIdFrom('42-d7-261010120000'));
        $this->assertNull(Epoint::adjustmentIdFrom('42-261010120000'));
        $this->assertNull(Epoint::adjustmentIdFrom(null));
        $this->assertNull(Epoint::adjustmentIdFrom(''));
    }

    /**
     * Paid on the second attempt: the bank answers about the first, and the
     * money must still settle on the extra rather than on the order.
     */
    public function test_an_extra_paid_on_a_retry_settles_on_the_extra(): void
    {
        $this->box();
        $order = $this->orderWith(null);
        $order->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();

        $extra = $order->adjustments()->create([
            'kind' => OrderAdjustment::CHARGE,
            'status' => OrderAdjustment::WAITING,
            'amount' => 2.00,
            'reason' => 'Qablaşdırma əlavə olundu',
        ]);

        // First attempt: its reference is written on the row.
        $first = Epoint::adjustmentReference($extra);
        $extra->forceFill(['epoint_ref' => $first])->save();

        // He comes back and tries again, which overwrites it.
        $second = Epoint::adjustmentReference($extra) . 'x';
        $extra->forceFill(['epoint_ref' => $second])->save();

        // The bank's word about the FIRST attempt arrives.
        $this->answer([
            'status' => 'success', 'order_id' => $first,
            'amount' => '2.00', 'transaction' => 'tx-first',
        ])->assertOk();

        $extra->refresh();
        $this->assertNotNull($extra->payment_confirmed_at, 'the extra is settled');
        $this->assertSame(OrderAdjustment::PAID, $extra->status);
        $this->assertSame('tx-first', $extra->epoint_transaction);
    }
}
