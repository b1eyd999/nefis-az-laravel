<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A basket that outlives the session it was made in.
 *
 * It lived in the session alone: the customer spent ten minutes making a
 * box, left it until the evening, came back on another telephone — and the
 * basket was empty, with all of that work gone.
 */
class SavedCartTest extends TestCase
{
    use RefreshDatabase;

    private function box(string $slug = 'love-story'): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => $slug, 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function customer(): User
    {
        return User::factory()->create([
            'email' => 'aygun@example.com',
            'password' => Hash::make('chocolate8'),
        ]);
    }

    /**
     * Another telephone: a session of its own and nobody signed in on it.
     *
     * Flushing the session alone is not enough — the guard in this process
     * still holds the user, and the basket would fill itself straight back
     * up, which is the very thing being tested.
     */
    private function anotherTelephone(): void
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        $this->assertGuest();
    }

    /** Signing in on the telephone he left at home. */
    private function signIn(): void
    {
        $this->post(route('login'), ['login' => 'aygun@example.com', 'password' => 'chocolate8'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_the_basket_is_waiting_on_the_other_telephone(): void
    {
        $box = $this->box();
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 2]);
        $this->assertSame(2, Cart::count());
        $this->assertSame(2, SavedCart::sole()->lines()[0]['quantity']);

        // Another telephone: another session, nothing carried over.
        $this->anotherTelephone();
        $this->assertSame(0, Cart::count());

        $this->signIn();

        $this->assertSame(2, Cart::count());
        $this->get(route('cart.index'))->assertOk()->assertSee('Love Story');
    }

    public function test_a_basket_made_before_signing_in_is_not_thrown_away(): void
    {
        $first = $this->box('love-story');
        $second = $this->box('kinder');
        $user = $this->customer();

        // Last week, signed in.
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $first->id]);
        $this->assertSame(1, Cart::count());

        // Today, on another telephone, not signed in yet.
        $this->anotherTelephone();
        $this->post(route('cart.add'), ['product_id' => $second->id]);
        $this->assertSame(1, Cart::count());
        $this->assertSame(1, SavedCart::count(), 'nothing is kept for a stranger');

        $this->signIn();

        // Both of them, and the kept copy says the same.
        $this->assertSame(2, Cart::count());
        $this->assertCount(2, SavedCart::sole()->lines());
    }

    public function test_signing_in_twice_does_not_double_the_basket(): void
    {
        $box = $this->box();
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);

        $this->anotherTelephone();
        $this->signIn();
        $this->assertSame(1, Cart::count());

        $this->post(route('logout'));
        app('auth')->forgetGuards();
        $this->signIn();
        $this->assertSame(1, Cart::count());
    }

    public function test_a_guest_basket_is_not_kept_anywhere(): void
    {
        $box = $this->box();

        $this->post(route('cart.add'), ['product_id' => $box->id]);
        $this->assertSame(1, Cart::count());
        $this->assertSame(0, SavedCart::count());
    }

    public function test_ordering_empties_what_was_kept_too(): void
    {
        PaymentAccount::create(['type' => PaymentAccount::CARD, 'label' => 'Kart', 'number' => '4169738111111111']);
        $box = $this->box();
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->assertCount(1, SavedCart::sole()->lines());

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '+994 55 123 45 67',
            'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        $this->assertSame([], SavedCart::sole()->lines());

        // And the next sign-in does not bring the ordered box back.
        $this->anotherTelephone();
        $this->signIn();
        $this->assertSame(0, Cart::count());
    }

    /** «Təcili» travels with it: it is part of what he asked for. */
    public function test_the_rush_he_asked_for_travels_with_the_basket(): void
    {
        $box = $this->box();
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        Cart::setRush(true);
        $this->assertTrue(SavedCart::sole()->rush);

        $this->anotherTelephone();
        $this->signIn();

        $this->assertTrue(Cart::rush());
    }
}
