<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoxPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_box_price_keeps_its_qepiks_from_the_panel_to_the_order(): void
    {
        $box = Product::create(['name' => 'Dark Spotify', 'slug' => 'dark-spotify', 'is_active' => true, 'price' => 5,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])
            ->fillForm(['price' => '4.90'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(4.9, $box->fresh()->price);
        $this->get(route('home'))->assertSee('4.90 ₼')->assertDontSee('>5 ₼<', false);
        $this->get(route('products.customize', $box->slug))->assertSee('4.90 ₼');

        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $this->assertSame(4.9, Order::firstOrFail()->items()->firstOrFail()->price);
    }
}
