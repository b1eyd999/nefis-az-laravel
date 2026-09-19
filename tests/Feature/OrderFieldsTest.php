<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\RelationManagers\ItemsRelationManager;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OrderFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function box(): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->photoSlots()->create(['label' => null, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1100, 'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);

        $base = ['x' => 10, 'y' => 10, 'max_width' => 500, 'font_size' => 40, 'color' => '#ffffff', 'align' => 'left', 'rotation' => 0,
            'max_lines' => 1, 'max_length' => 60, 'kind' => 'text', 'fixed' => false];
        $box->textSlots()->create($base + ['label' => 'Mətn 1', 'default_value' => 'Test', 'sort_order' => 0]);
        $box->textSlots()->create($base + ['label' => 'Ad', 'default_value' => 'Aysel', 'link_key' => 'ad', 'sort_order' => 1]);
        $box->textSlots()->create($base + ['label' => 'Ad', 'default_value' => 'Aysel', 'link_key' => 'ad', 'sort_order' => 2]);
        $box->textSlots()->create(array_merge($base, ['label' => 'Başlıq', 'default_value' => 'Special edition For', 'fixed' => true, 'sort_order' => 3]));
        $box->textSlots()->create(array_merge($base, ['label' => null, 'kind' => 'time', 'default_value' => '00:34', 'max_length' => 5, 'sort_order' => 4]));

        return $box;
    }

    private function order(Product $box): Order
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), [
            'product_id' => $box->id,
            'photos' => [UploadedFile::fake()->image('me.jpg', 50, 50)],
            'custom_texts' => ['Salam', 'Aysel', 'Aysel', 'ignored', '03:15'],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('checkout.store'), [
            'contact_phone' => '+994 50 000 00 00', 'delivery_address' => 'Bakı',
        ])->assertRedirect(route('orders.index'));

        return Order::firstOrFail();
    }

    public function test_an_order_keeps_the_names_of_the_fields_the_customer_filled(): void
    {
        $item = $this->order($this->box())->items()->firstOrFail();

        $fields = $item->fields();
        $this->assertSame('1. Şəkil', $fields['photos'][0]['label']);
        Storage::disk('public')->assertExists($fields['photos'][0]['path']);

        // The linked repeat is listed once; the fixed caption is marked.
        $this->assertSame([
            ['label' => 'Mətn 1', 'value' => 'Salam', 'fixed' => false],
            ['label' => 'Ad', 'value' => 'Aysel', 'fixed' => false],
            ['label' => 'Başlıq', 'value' => 'Special edition For', 'fixed' => true],
            ['label' => 'Mətn', 'value' => '03:15', 'fixed' => false],
        ], $fields['texts']);
    }

    public function test_the_names_survive_the_design_being_changed_or_deleted(): void
    {
        $box = $this->box();
        $item = $this->order($box)->items()->firstOrFail();

        $box->delete();

        $this->assertSame('Mətn 1', $item->fresh()->fields()['texts'][0]['label']);
    }

    public function test_older_orders_borrow_the_names_from_the_design(): void
    {
        $box = $this->box();
        $order = Order::create(['user_id' => User::factory()->create()->id, 'status' => 'pending', 'contact_phone' => '1', 'delivery_address' => 'x']);
        $item = $order->items()->create(['product_id' => $box->id, 'product_name' => 'Test', 'customer_photos' => ['cart-photos/a.jpg'],
            'custom_texts' => ['Salam', 'Aysel', 'Aysel', 'Special edition For', '03:15'], 'quantity' => 1]);

        $this->assertSame('Mətn 1', $item->fields()['texts'][0]['label']);
        $this->assertSame('1. Şəkil', $item->fields()['photos'][0]['label']);
    }

    public function test_the_admin_sees_each_field_under_its_name(): void
    {
        $order = $this->order($this->box());
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $order, 'pageClass' => EditOrder::class])
            ->assertSeeInOrder(['1. Şəkil', 'Yüklə', 'Mətn 1', 'Salam', 'Ad', 'Aysel', 'Başlıq', 'dizaynda sabit', 'Special edition For', '03:15'])
            ->assertDontSee('Salam, Aysel');
    }
}
