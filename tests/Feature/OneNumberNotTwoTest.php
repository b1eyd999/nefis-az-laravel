<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The checkout asks a guest for his telephone number once.
 *
 * He typed it into «Əlaqə nömrəsi» at the top of the form; four fields and a
 * map later, «Telefon nömrəsi» was empty and required. Either he concluded
 * the shop had not been listening, or that this one was meant to be somebody
 * else's number — and a required field he does not understand is where a
 * form ends.
 */
class OneNumberNotTwoTest extends TestCase
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

    public function test_a_guest_is_asked_for_his_number_once(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $page = $this->get(route('checkout.index'))->assertOk();
        $page->assertSee('name="guest_phone"', false);
        $page->assertDontSee('name="contact_phone"', false);
    }

    /** And the order carries that one number. */
    public function test_the_number_he_gave_reaches_the_order(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_address' => 'Bakı, Nizami küç. 5',
            'guest_name' => 'Aysel',
            'guest_email' => 'aysel@example.com',
            'guest_phone' => '055 555 55 55',
            // contact_phone deliberately absent: the field is gone for a guest
        ])->assertSessionHasNoErrors()->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame('055 555 55 55', $order->contact_phone);
        $this->assertSame('+994 55 555 55 55', $order->user->phone);
    }

    /** A signed-in customer still has his own field, and it is still required. */
    public function test_a_signed_in_customer_keeps_the_field(): void
    {
        $user = User::factory()->create(['phone' => '+994 50 111 22 33']);
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->actingAs($user)->get(route('checkout.index'))->assertOk()
            ->assertSee('name="contact_phone"', false)
            ->assertDontSee('name="guest_phone"', false);

        $this->actingAs($user)->from(route('checkout.index'))
            ->post(route('checkout.store'), [
                'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
                'delivery_address' => 'Bakı, Nizami küç. 5',
            ])->assertSessionHasErrors('contact_phone');

        $this->assertSame(0, Order::count());
    }
}
