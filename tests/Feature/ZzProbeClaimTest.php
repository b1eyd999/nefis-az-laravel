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

class ZzProbeClaimTest extends TestCase
{
    use RefreshDatabase;

    private function box(float $price = 20): Product
    {
        $box = Product::create(['name' => 'T'.uniqid(), 'slug' => 't'.uniqid(), 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        return $box;
    }

    private function place(int $percent, int $boxes): Order
    {
        PromoCode::create(['code' => 'PR' . $percent, 'percent' => $percent]);
        $box = $this->box(20);
        $user = User::factory()->create();
        for ($i = 0; $i < $boxes; $i++) {
            $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        }
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
            'promo_code' => 'PR' . $percent,
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail()->load('items');
    }

    public function test_probe(): void
    {
        foreach ([100, 50] as $percent) {
            $order = $this->place($percent, 2);
            $order->forceFill(['status' => 'confirmed', 'payment_confirmed_at' => now(),
                'delivery_price' => 5, 'rush_fee' => 0])->save();
            $order = $order->fresh()->load('items');

            $before = $order->total();
            $money = OrderEditor::change($order, 'Sətir silindi',
                fn () => $order->items()->latest('id')->first()->delete());
            $order = $order->fresh()->load('items', 'adjustments');

            $report = Accounting::report();
            $row = collect($report['rows'] ?? [])->first(fn ($r) => $r['order']->id === $order->id);

            fwrite(STDERR, sprintf(
                "\n=== %d%% code, 2x20M, delivery 5 ===\n".
                "stored discount=%.2f  itemsTotal after=%.2f  discountOff=%.2f\n".
                "total BEFORE edit=%.2f   total AFTER edit=%.2f\n".
                "adjustment=%s  amount=%.2f\n".
                "owedBack=%.2f outstanding=%.2f paidSoFar=%.2f\n".
                "BOOKS goods=%.2f revenue=%.2f\n",
                $percent, (float) $order->discount, $order->itemsTotal(), $order->discountOff(),
                $before, $order->total(),
                $money ? $money->kind : 'NONE', $money ? (float) $money->amount : 0,
                $order->owedBack(), $order->outstanding(), $order->paidSoFar(),
                (float) ($row['goods'] ?? NAN), (float) ($row['revenue'] ?? NAN)
            ));
        }
        $this->assertTrue(true);
    }
}
