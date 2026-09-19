<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\MaterialResource\Pages\ListMaterials;
use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Expense;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Accounting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private function paper(): Material
    {
        return Material::where('name', 'Qutu kağızı')->firstOrFail();
    }

    private function glue(): Material
    {
        return Material::where('name', 'Yapışqan')->firstOrFail();
    }

    /** A customer orders `$boxes` boxes at 4 ₼ with a bar the owner buys for 2.69 ₼ and sells for 4.04 ₼. */
    private function order(int $boxes): Order
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $bar = Chocolate::firstOrCreate(['name' => 'Milka 90 q'], ['base_price' => 4.50, 'sale_price' => 2.69]);
        $door = DeliveryMethod::where('type', 'door')->first();
        $door->update(['price' => 5]);

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'chocolate_id' => $bar->id, 'quantity' => $boxes])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        return Order::latest('id')->firstOrFail();
    }

    public function test_one_box_costs_a_sheet_of_paper_and_a_fiftieth_of_the_glue(): void
    {
        // 9.80 ₼ for 50 sheets, 0.80 ₼ of glue for about 50 boxes.
        $this->assertEqualsWithDelta(0.196, $this->paper()->costPerBox(), 0.0001);
        $this->assertEqualsWithDelta(0.016, $this->glue()->costPerBox(), 0.0001);
        $this->assertEqualsWithDelta(0.212, Material::costOfOneBox(), 0.0001);
    }

    public function test_orders_take_materials_out_of_stock_and_cancelling_puts_them_back(): void
    {
        Accounting::purchase($this->paper(), 2, 9.80);
        $this->assertSame(100.0, $this->paper()->fresh()->stock);

        $order = $this->order(3);
        $this->assertSame(97.0, $this->paper()->fresh()->stock);
        $this->assertSame(-3.0, $this->glue()->fresh()->stock, 'no glue bought yet: the shortfall shows');
        $this->assertEqualsWithDelta(0.64, $order->materials_cost, 0.001);   // 3 × 0.212
        $this->assertSame(2.69, $order->items->first()->chocolate_cost);

        $order->update(['status' => 'cancelled']);
        $this->assertSame(100.0, $this->paper()->fresh()->stock);

        $order->update(['status' => 'confirmed']);
        $this->assertSame(97.0, $this->paper()->fresh()->stock);
    }

    public function test_the_books_show_the_real_profit_shared_in_three(): void
    {
        Accounting::purchase($this->paper(), 1, 9.80);
        $order = $this->order(2);                       // 2 × (4 + 4.04 bar) + 5 delivery
        $cancelled = $this->order(1);
        $cancelled->update(['status' => 'cancelled']);
        Expense::create(['spent_on' => now(), 'category' => 'Reklam', 'amount' => 3]);

        $bar = Chocolate::first();
        $r = Accounting::report();

        $this->assertSame(1, $r['orders']);
        $this->assertEqualsWithDelta(2 * (4 + $bar->price()) + 5, $r['revenue'], 0.001);
        $this->assertEqualsWithDelta(2 * 2.69, $r['chocolate'], 0.001);
        $this->assertEqualsWithDelta(0.42, $r['materials'], 0.001);
        $this->assertSame(3.0, $r['expenses']);
        $net = $r['revenue'] - 5.38 - 0.42 - 3;
        $this->assertEqualsWithDelta($net, $r['net'], 0.01);
        $this->assertCount(3, $r['shares']);
        $this->assertEqualsWithDelta($net, array_sum(array_column($r['shares'], 'amount')), 0.02);
        $this->assertEqualsWithDelta($r['revenue'] - 5.38 - 9.80 - 3, $r['cash'], 0.01);
    }

    public function test_the_site_can_be_closed_for_maintenance(): void
    {
        Setting::put(Setting::MAINTENANCE, true);
        Setting::put(Setting::MAINTENANCE_MESSAGE, 'Yeni il üçün hazırlaşırıq');

        $this->get(route('home'))->assertStatus(503)->assertSee('Yeni il üçün hazırlaşırıq');
        $this->get(route('designs.index'))->assertStatus(503);
        $this->get(route('login'))->assertOk();

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get(route('home'))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('home'))->assertStatus(503);

        Setting::put(Setting::MAINTENANCE, false);
        $this->get(route('home'))->assertOk();
    }

    public function test_the_owner_runs_it_all_from_the_admin(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (['/admin/balance', '/admin/materials', '/admin/expenses', '/admin/settings'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/materials')->assertSee(['Qutu kağızı', 'Yapışqan', '0.21 ₼']);

        // Buying stock from the list.
        Livewire::test(ListMaterials::class)
            ->callTableAction('purchase', $this->paper(), ['packs' => 3, 'price' => 10])
            ->assertHasNoTableActionErrors();
        $this->assertSame(150.0, $this->paper()->fresh()->stock);
        $this->assertSame(10.0, $this->paper()->fresh()->pack_price);

        // Shares must add up to 100; the switch closes the site.
        Livewire::test(SiteSettings::class)
            ->fillForm(['maintenance' => true, 'maintenance_message' => 'Bağlıyıq', 'shares' => [
                ['name' => 'Mən', 'percent' => 50], ['name' => 'Aysel', 'percent' => 30],
            ]])
            ->call('save');
        $this->assertSame('0', Setting::get(Setting::MAINTENANCE), 'refused: 80 %');

        Livewire::test(SiteSettings::class)
            ->fillForm(['maintenance' => true, 'maintenance_message' => 'Bağlıyıq', 'shares' => [
                ['name' => 'Mən', 'percent' => 50], ['name' => 'Aysel', 'percent' => 25], ['name' => 'Kamran', 'percent' => 25],
            ]])
            ->call('save');
        $this->assertSame('1', Setting::get(Setting::MAINTENANCE));
        $this->assertSame(['Mən', 'Aysel', 'Kamran'], array_column(Setting::profitShares(), 'name'));

        $manager = User::factory()->create(['role' => User::MANAGER]);
        foreach (['/admin/balance', '/admin/materials', '/admin/expenses', '/admin/settings'] as $url) {
            $this->actingAs($manager)->get($url)->assertForbidden();
        }
    }
}
