<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryMethodResource\Pages\ListDeliveryMethods;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['phone' => '+994500000000']);
        DeliveryMethod::where('type', DeliveryMethod::DOOR)->update(['price' => 5]);
        DeliveryMethod::where('type', DeliveryMethod::POST)->update(['price' => 3.5]);
        DeliveryMethod::where('type', DeliveryMethod::METRO)->update(['price' => 2]);
    }

    private function method(string $type): DeliveryMethod
    {
        return DeliveryMethod::where('type', $type)->firstOrFail();
    }

    /** A cart holding one 4 ₼ box. */
    private function fillCart(): void
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
    }

    public function test_checkout_offers_the_three_ways_with_their_prices(): void
    {
        $this->fillCart();

        $this->actingAs($this->customer)->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeInOrder(['Qapıya çatdırılma', '5 ₼', 'Poçt ilə çatdırılma', '3.50 ₼', 'Metroya çatdırılma', '2 ₼'])
            ->assertSee('Yalnız Bakı daxilində')
            ->assertSee('Poçt şöbəsinin indeksi')
            ->assertSee('28 May');

        $this->actingAs($this->customer)->post(route('checkout.store'), ['contact_phone' => '1'])
            ->assertSessionHasErrors('delivery_method_id');
    }

    public function test_door_delivery_needs_the_address(): void
    {
        $this->fillCart();
        $door = $this->method(DeliveryMethod::DOOR);

        $this->actingAs($this->customer)->post(route('checkout.store'), ['delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1'])
            ->assertSessionHasErrors('delivery_address');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1', 'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10',
        ])->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame(['door', 'Qapıya çatdırılma', 5.0, 'Nəsimi r., Rəşid Behbudov 10'],
            [$order->delivery_type, $order->delivery_name, $order->delivery_price, $order->delivery_address]);
        $this->assertSame(9.0, $order->total());
    }

    public function test_post_delivery_needs_the_name_phone_and_post_office_index(): void
    {
        $this->fillCart();
        $post = $this->method(DeliveryMethod::POST);

        $this->actingAs($this->customer)->post(route('checkout.store'), ['delivery_method_id' => $post->id, 'contact_phone' => '1'])
            ->assertSessionHasErrors(['recipient_name', 'postal_index']);
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $post->id, 'contact_phone' => '1', 'recipient_name' => 'Aysel Məmmədova', 'postal_index' => 'Bakı',
        ])->assertSessionHasErrors('postal_index');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $post->id, 'contact_phone' => '+994 50 1', 'recipient_name' => 'Aysel Məmmədova', 'postal_index' => 'az 2001',
        ])->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame(['post', 'Aysel Məmmədova', 'AZ2001', 3.5], [$order->delivery_type, $order->recipient_name, $order->postal_index, $order->delivery_price]);
        $this->assertSame('Aysel Məmmədova, poçt indeksi AZ2001', $order->deliverySummary());

        // The order page in the admin shows it all.
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/orders/' . $order->id . '/edit')
            ->assertOk()
            ->assertSee(['Poçt ilə çatdırılma', '3.50 ₼', 'Aysel Məmmədova', 'AZ2001', '7.50 ₼']);
        $this->get('/admin/orders')->assertSee('Poçt ilə çatdırılma');
    }

    public function test_metro_delivery_needs_a_station_from_the_list(): void
    {
        $this->fillCart();
        $metro = $this->method(DeliveryMethod::METRO);

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $metro->id, 'contact_phone' => '1', 'metro_station' => 'Paris Nord',
        ])->assertSessionHasErrors('metro_station');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $metro->id, 'contact_phone' => '1', 'metro_station' => 'Gənclik',
        ])->assertRedirect(route('orders.index'));

        $this->assertSame('Metro: Gənclik', Order::firstOrFail()->deliverySummary());
        $this->actingAs($this->customer)->get(route('orders.index'))->assertSee('Metro: Gənclik')->assertSee('Cəmi: 6 ₼', false);
    }

    public function test_a_switched_off_way_is_neither_shown_nor_accepted(): void
    {
        $this->fillCart();
        $door = $this->method(DeliveryMethod::DOOR);
        $door->update(['is_active' => false]);

        $this->actingAs($this->customer)->get(route('checkout.index'))->assertDontSee('Qapıya çatdırılma');
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasErrors('delivery_method_id');
    }

    public function test_the_owner_prices_the_ways_in_the_admin(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/delivery-methods')->assertOk()->assertSee(['Qapıya çatdırılma', 'Poçt ilə çatdırılma', 'Metroya çatdırılma']);

        $door = $this->method(DeliveryMethod::DOOR);
        Livewire::test(ListDeliveryMethods::class)->call('updateTableColumnState', 'price', (string) $door->id, '6.50');
        $this->assertSame(6.5, $door->fresh()->price);

        $manager = User::factory()->create(['role' => User::MANAGER]);
        $this->actingAs($manager)->get('/admin/delivery-methods')->assertForbidden();
    }
}
