<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\BestSellers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The two things the shop says about itself from other people's orders: the
 * notice in the corner ("someone just ordered this") and the flame on the
 * designs that are actually selling.
 */
class ShopFrontProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function sold(string $name, int $quantity, string $buyer = 'İlkin Kazımlı', int $daysAgo = 0): Product
    {
        $product = Product::create(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name),
            'is_active' => true, 'price' => 4.90]);

        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'confirmed',
            'recipient_name' => $buyer, 'contact_phone' => '1']);
        $order->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id,
            'product_name' => $name, 'quantity' => $quantity, 'unit_price' => 4.90]);

        Cache::flush();

        return $product;
    }

    public function test_the_corner_notice_says_who_ordered_what_without_naming_them(): void
    {
        $this->sold('Love Story Vol 1', 2);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('İlkin K.', $html, 'a first name and one initial');
        $this->assertStringNotContainsString('Kazımlı', $html, 'the surname stays out of the shop window');
        $this->assertStringContainsString('Love Story Vol 1', $html);
        $this->assertStringContainsString('"qty":2', $html);

        // The basket is left alone: nobody needs a distraction while paying.
        $this->assertStringNotContainsString('sale-toast', $this->get(route('cart.index'))->getContent());

        // And the owner can switch it off altogether.
        Setting::put(Setting::SALE_TOASTS, '0');
        $this->assertStringNotContainsString('sale-toast', $this->get(route('home'))->getContent());
    }

    public function test_a_cancelled_or_old_order_is_not_shown_as_proof(): void
    {
        $this->sold('Köhnə dizayn', 1, 'Aysel Məmmədova', 40);          // too long ago
        $fresh = $this->sold('Ləğv edilmiş', 1, 'Tofig Hacıyev');
        Order::latest('id')->first()->update(['status' => 'cancelled']);
        Cache::flush();

        // The design names live in the catalogue too, so the notice itself is
        // what gets read here, not the whole page.
        $shown = collect(\App\Support\RecentSales::all())->pluck('what')->all();

        $this->assertSame([], $shown, 'nothing old and nothing cancelled is paraded as proof');
        $this->assertFalse(BestSellers::has($fresh->id), 'a cancelled order sells nothing');
        $this->assertStringNotContainsString('Aysel', $this->get(route('home'))->getContent());
    }

    public function test_the_flame_follows_what_is_selling(): void
    {
        $quiet = Product::create(['name' => 'Heç kim almır', 'slug' => 'hec-kim-almir', 'is_active' => true, 'price' => 4.90]);
        $hot = $this->sold('Ən çox satılan', 9);
        $this->sold('Bir dənə satılan', 1);

        $this->assertSame($hot->id, BestSellers::ids()[0] ?? null, 'the best seller comes first');
        $this->assertFalse(BestSellers::has($quiet->id), 'a design nobody bought wears nothing');

        // The home page is where the cards live; the flame sits on the one that sells.
        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="p-hot"[\s\S]{0,600}Ən çox satılan/u', $html);
        $this->assertSame(2, substr_count($html, 'class="p-hot"'), 'both designs that sold, and nothing else');

        // Sales stop counting after a season, so the flame does not stay for ever.
        \App\Models\Order::query()->update(['created_at' => now()->subDays(BestSellers::DAYS + 1)]);
        BestSellers::forget();
        $this->assertSame([], BestSellers::ids());
    }
}
