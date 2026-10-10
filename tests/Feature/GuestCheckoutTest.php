<?php

namespace Tests\Feature;

use App\Mail\WelcomePassword;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Ordering without signing up first.
 *
 * The shop lost people at the sign-up: a visitor who had chosen a box, put
 * his photograph on it and written his words was then asked to invent a
 * password before he could pay. He gives his name, number and e-mail, and
 * the account is made out of those.
 */
class GuestCheckoutTest extends TestCase
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

    /** @return array<string, mixed> */
    private function form(array $over = []): array
    {
        return array_replace([
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '+994 55 555 55 55',
            'delivery_address' => 'Bakı, Nizami küç. 5',
            'guest_name' => 'Aysel Məmmədova',
            'guest_email' => 'Aysel@Example.COM',
            'guest_phone' => '055 555 55 55',
        ], $over);
    }

    public function test_the_checkout_opens_for_a_visitor_with_no_account(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('name="guest_email"', false)
            ->assertSee(__('Hesabınız var? Daxil olun'));
    }

    /** A customer who is signed in is never asked for any of it. */
    public function test_a_signed_in_customer_sees_none_of_those_fields(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->actingAs(User::factory()->create())
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertDontSee('name="guest_email"', false);
    }

    public function test_a_guest_orders_and_the_account_is_made_for_him(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->post(route('checkout.store'), $this->form())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user = User::firstOrFail();
        $this->assertSame('aysel@example.com', $user->email, 'the address is kept as the shop reads it');
        $this->assertSame('Aysel Məmmədova', $user->name);
        $this->assertSame('+994 55 555 55 55', $user->phone, 'and the number in the one shape the shop writes');
        $this->assertSame(User::CUSTOMER, $user->role);

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id, 'the order belongs to him');

        // And he is in: the payment page is his to reach.
        $this->assertTrue(auth()->check());
        $this->get(route('orders.pay', $order))->assertOk();
    }

    /** The letter that tells him the account exists and how to get in. */
    public function test_he_is_written_to_with_a_link_for_setting_a_password(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);
        $this->post(route('checkout.store'), $this->form());

        Mail::assertSent(WelcomePassword::class, fn (WelcomePassword $m) => $m->hasTo('aysel@example.com'));
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'aysel@example.com']);

        // The token really opens the door.
        $token = null;
        Mail::assertSent(WelcomePassword::class, function (WelcomePassword $m) use (&$token) {
            $token = $m->token;

            return true;
        });
        auth()->logout();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'aysel@example.com',
            'password' => 'yeni-sifre-2026', 'password_confirmation' => 'yeni-sifre-2026',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(auth()->check());
    }

    /**
     * An e-mail the shop already knows is not quietly taken over: that would
     * hand one customer's order history to whoever typed his address.
     */
    public function test_a_known_address_is_sent_to_the_door_instead(): void
    {
        User::factory()->create(['email' => 'aysel@example.com', 'phone' => '+994701112233']);
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->form())
            ->assertSessionHasErrors('guest_email');

        $this->assertSame(0, Order::count(), 'and no order was written');
        $this->assertSame(1, User::count(), 'nor a second account');
        // The basket is still his when he comes back.
        $this->assertCount(1, Cart::items());
    }

    public function test_a_known_number_is_refused_the_same_way(): void
    {
        User::factory()->create(['email' => 'basqa@example.com', 'phone' => '+994 55 555 55 55']);
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->form())
            ->assertSessionHasErrors('guest_phone');
        $this->assertSame(0, Order::count());
    }

    public function test_the_three_fields_are_required_and_the_number_is_checked(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->form([
                'guest_name' => '', 'guest_email' => '', 'guest_phone' => '',
            ]))
            ->assertSessionHasErrors(['guest_name', 'guest_email', 'guest_phone']);

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->form(['guest_phone' => '12']))
            ->assertSessionHasErrors('guest_phone');

        $this->assertSame(0, User::count());
        $this->assertSame(0, Order::count());
    }

    /**
     * A form that fails on the delivery leaves no account behind.
     *
     * The order is what he came for; half of one must not cost him an
     * account he then cannot sign up with again.
     */
    public function test_a_refused_delivery_leaves_no_account_behind(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->form(['delivery_address' => '']))
            ->assertSessionHasErrors('delivery_address');

        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        Mail::assertNothingSent();
    }

    /** What he built before there was anybody to keep it for is kept now. */
    public function test_the_basket_is_kept_for_him_once_he_exists(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);
        $this->post(route('checkout.store'), $this->form());

        // The order emptied the basket, but the row is his and is now empty
        // rather than missing — which is what tells the shop it is his.
        $this->assertDatabaseHas('saved_carts', ['user_id' => User::firstOrFail()->id]);
    }

    /** Signing in from the checkout comes back to the checkout. */
    public function test_the_door_sends_him_back_where_he_was(): void
    {
        $user = User::factory()->create(['email' => 'var@example.com']);
        $this->post(route('cart.add'), ['product_id' => $this->box()->id]);

        $this->get(route('checkout.index'))->assertOk();
        $this->post(route('login'), ['login' => 'var@example.com', 'password' => 'password'])
            ->assertRedirect(route('checkout.index'));
    }
}
