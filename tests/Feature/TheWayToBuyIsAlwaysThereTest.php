<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Two places where the way forward was there but out of reach.
 *
 * The design page is seven and a half phone screens tall and the button
 * that buys sits on the fourth; and door delivery could not be ordered at
 * all unless a map — loaded from somebody else's server, with no timeout —
 * gave up a pin.
 */
class TheWayToBuyIsAlwaysThereTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        PaymentAccount::firstOrCreate(['type' => PaymentAccount::CARD],
            ['label' => 'Kart', 'number' => '4169738111111111']);
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

    /** The phone always has a way to buy on screen. */
    public function test_the_design_page_carries_a_bar_that_buys(): void
    {
        $page = $this->get(route('products.customize', $this->box()->slug))->assertOk();

        $page->assertSee('id="buy-bar"', false);
        $page->assertSee('id="buy-bar-btn"', false);
        // It is a second submit of the same form, not a link somewhere else.
        $page->assertSee('<button type="submit" class="btn btn-primary btn-flame" id="buy-bar-btn"', false);
    }

    /** The map is a convenience; the address is what the courier drives to. */
    public function test_a_written_address_is_enough_for_door_delivery(): void
    {
        $door = DeliveryMethod::where('type', 'door')->firstOrFail();
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->post(route('checkout.store'), [
            'delivery_method_id' => $door->id,
            'delivery_address' => 'Bakı, Nizami küç. 5, mənzil 12',
            // no delivery_lat / delivery_lng: the map never opened
            'guest_name' => 'Aysel',
            'guest_email' => 'aysel@example.com',
            'guest_phone' => '055 555 55 55',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('Bakı, Nizami küç. 5, mənzil 12', $order->delivery_address);
        $this->assertNull($order->delivery_lat, 'and no pin was invented for him');
    }

    /** The page gives the map a clock, and says so when it runs out. */
    public function test_the_map_is_raced_against_a_clock(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->get(route('checkout.index'))->assertOk()
            ->assertSee('setTimeout(function(){', false)
            // Blade's @json escapes the Azerbaijani letters, so the page is
            // searched for exactly what it prints.
            ->assertSee(json_encode(__('Xəritə açılmadı — ünvanı aşağıda yazın, kifayətdir.')), false)
            // and the submit no longer demands a pin when an address is written
            ->assertSee('!lat.value && !written', false);
    }
}
