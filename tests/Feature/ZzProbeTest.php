<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZzProbeTest extends TestCase
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

    private function place(Product $box, string $code): Order
    {
        $u = User::factory()->create();
        $this->actingAs($u)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($u)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => $code,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail();
    }

    /** A: abandoned payment — does it burn the single use? */
    public function test_abandoned_payment_does_not_burn_the_use(): void
    {
        $promo = PromoCode::create(['code' => 'ONE', 'percent' => 50, 'max_uses' => 1]);
        $box = $this->box(20);

        $a = $this->place($box, 'ONE');
        echo "\n[A] after checkout: status={$a->status} discount={$a->discount} used_count=".$promo->fresh()->used_count."\n";

        // the ePoint page is closed; the hourly command cancels it
        $a->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->saveQuietly();
        $this->artisan('orders:expire-unpaid');
        echo "[A] after expiry: status=".$a->fresh()->status." used_count=".$promo->fresh()->used_count."\n";

        // the code was made for this customer
        $this->assertNull($promo->fresh()->refusal(20.0), 'intended customer must still be able to use it');
    }

    /** B: two orders both placed, then both actually paid. */
    public function test_two_paid_orders_against_max_uses_one(): void
    {
        $promo = PromoCode::create(['code' => 'ONE', 'percent' => 50, 'max_uses' => 1]);
        $box = $this->box(20);

        $a = $this->place($box, 'ONE');
        $b = $this->place($box, 'ONE');
        echo "\n[B] discounts frozen: a={$a->discount} b={$b->discount} used_count=".$promo->fresh()->used_count."\n";

        $a->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();
        echo "[B] after A pays: used_count=".$promo->fresh()->used_count."\n";
        $b->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();
        echo "[B] after B pays: used_count=".$promo->fresh()->used_count
            ." (max_uses=1), discounted orders=".Order::where('discount', '>', 0)->count()."\n";
    }

    /** C: owner re-confirms the same payment twice. */
    public function test_reconfirming_the_same_order(): void
    {
        $promo = PromoCode::create(['code' => 'ONE', 'percent' => 50, 'max_uses' => 1]);
        $box = $this->box(20);
        $a = $this->place($box, 'ONE');

        $a->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();
        echo "\n[C] first confirm: used_count=".$promo->fresh()->used_count."\n";
        $a->forceFill(['payment_confirmed_at' => null, 'status' => 'awaiting_payment'])->save();
        $a->forceFill(['payment_confirmed_at' => now(), 'status' => 'confirmed'])->save();
        echo "[C] cleared and confirmed again: used_count=".$promo->fresh()->used_count."\n";
    }
}
