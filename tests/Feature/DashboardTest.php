<?php

namespace Tests\Feature;

use App\Filament\Widgets\ShopStats;
use App\Filament\Widgets\StockLeft;
use App\Filament\Widgets\TopDesigns;
use App\Models\DeliveryMethod;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel opens on the numbers that matter: the month's orders, what sells
 * and what is left in the store room.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function sell(string $design, int $times): void
    {
        $customer = User::factory()->create();
        $box = Product::create(['name' => $design, 'slug' => \Illuminate\Support\Str::slug($design), 'is_active' => true,
            'price' => 5, 'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $door->update(['price' => 5, 'is_active' => true]);

        for ($i = 0; $i < $times; $i++) {
            $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id]);
            $this->actingAs($customer)->post(route('checkout.store'), [
                'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1',
                'delivery_address' => 'Bakı, Nəsimi 1',
            ])->assertRedirect();
        }
    }

    public function test_the_owner_sees_the_month_what_sells_and_what_is_left(): void
    {
        $this->sell('Love Story', 3);
        $this->sell('Kinder', 1);
        Material::create(['name' => 'Karton qutu', 'unit' => 'ədəd', 'pack_price' => 20, 'pack_size' => 50,
            'per_box' => 1, 'stock' => 6, 'low_stock' => 10, 'is_active' => true]);
        Material::create(['name' => 'Lent', 'unit' => 'm', 'pack_price' => 10, 'pack_size' => 100,
            'per_box' => 0.5, 'stock' => 90, 'low_stock' => 10, 'is_active' => true]);

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ShopStats::class)
            ->assertSee('Bu ay sifariş')
            ->assertSee('4')                          // three Love Story plus one Kinder
            ->assertSee('Bu ayın gəliri')
            ->assertSee('Gözləyən sifariş');

        Livewire::test(TopDesigns::class)
            ->assertSee('Love Story')
            ->assertSee('Kinder')
            ->assertCanSeeTableRecords(Order::firstOrFail()->items);   // the table renders its rows

        Livewire::test(StockLeft::class)
            ->assertSee('Karton qutu')                 // 6 left, under its own low mark: shown first
            ->assertSee('Lent')
            ->assertSee('6 ədəd');
    }

    public function test_a_manager_sees_the_work_but_not_the_money(): void
    {
        $this->sell('Love Story', 1);

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ShopStats::class)
            ->assertSee('Bu ay sifariş')
            ->assertDontSee('Bu ayın gəliri');
    }
}
