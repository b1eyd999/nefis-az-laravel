<?php

namespace Tests\Feature;

use App\Filament\Pages\Balance;
use App\Mail\OrderStatus;
use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Accounting;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Money given back: the order leaves the books, its materials go back on the
 * shelf, and the customer is told.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    /** One box at 4 ₼ with a bar and 5 ₼ delivery, paid for. */
    private function order(): Order
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $bar = Chocolate::firstOrCreate(['name' => 'Milka 90 q'], ['base_price' => 4.50, 'sale_price' => 2.69]);
        $door = DeliveryMethod::where('type', 'door')->first();
        $door->update(['price' => 5]);

        $user = User::factory()->create(['email' => 'musteri@example.com']);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'chocolate_id' => $bar->id, 'quantity' => 1]);
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        $order = Order::latest('id')->firstOrFail();
        $order->forceFill(['status' => 'confirmed'])->save();

        return $order->fresh();
    }

    public function test_a_refunded_order_leaves_the_books_and_its_materials_go_back(): void
    {
        Setting::put(Setting::NOTIFY_EMAIL, '1');
        Mail::fake();

        $order = $this->order();
        $paper = Material::where('name', 'Qutu kağızı')->firstOrFail();
        $stockWhileSold = $paper->fresh()->stock;

        $earned = Accounting::report();
        $this->assertGreaterThan(0, $earned['revenue']);
        $this->assertSame(1, $earned['orders']);

        $order->forceFill(['status' => 'refunded'])->save();

        $after = Accounting::report();
        $this->assertSame(0, $after['orders'], 'a refunded order is not a sale');
        $this->assertSame(0.0, $after['revenue']);
        $this->assertSame(0.0, $after['net']);

        // The paper it took is back on the shelf.
        $this->assertGreaterThan($stockWhileSold, $paper->fresh()->stock);

        // And the customer hears about it, in the words of the refund.
        Mail::assertSent(OrderStatus::class, fn (OrderStatus $mail) => $mail->order->is($order));
        $this->assertStringContainsString('qaytard', \App\Support\CustomerNotice::line($order->fresh()));
    }

    public function test_bringing_a_refunded_order_back_makes_it_a_sale_again(): void
    {
        $order = $this->order();
        $order->forceFill(['status' => 'refunded'])->save();
        $this->assertSame(0, Accounting::report()['orders']);

        $order->forceFill(['status' => 'completed'])->save();

        $back = Accounting::report();
        $this->assertSame(1, $back['orders']);
        $this->assertGreaterThan(0, $back['revenue']);
    }

    public function test_the_owner_can_start_the_books_from_today(): void
    {
        $order = $this->order();
        $this->assertSame(1, Accounting::report()['orders']);

        $admin = User::factory()->create(['is_admin' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($admin)->test(Balance::class)
            ->callAction('zero', data: ['at' => now()->addMinute()->format('Y-m-d\TH:i')])
            ->assertHasNoActionErrors();

        $zeroed = Accounting::report();
        $this->assertSame(0, $zeroed['orders'], 'everything before the line is another chapter');
        $this->assertSame(0.0, $zeroed['revenue']);
        // Nothing was deleted.
        $this->assertDatabaseHas('orders', ['id' => $order->id]);

        Livewire::actingAs($admin)->test(Balance::class)->callAction('unzero');

        $this->assertSame(1, Accounting::report()['orders'], 'and it all comes back');
    }

    public function test_a_manager_cannot_zero_the_books(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $manager = User::factory()->create(['role' => User::MANAGER]);

        Livewire::actingAs($manager)->test(Balance::class)->assertActionDoesNotExist('zero');
    }

    public function test_the_status_is_offered_wherever_an_order_is_changed(): void
    {
        $this->assertArrayHasKey('refunded', Order::STATUSES);
        $this->assertSame('Vəsait qaytarıldı', Order::STATUSES['refunded']);
        $this->assertContains('refunded', Order::OFF_THE_BOOKS);
    }
}
