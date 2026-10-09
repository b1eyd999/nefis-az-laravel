<?php

namespace Tests\Feature;

use App\Models\CartHandoff;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A basket the shop filled, put straight into a customer's own.
 *
 * The link was the only way across: the owner built the box, copied an
 * address and got it to the customer himself. When the customer already has
 * an account there is nothing to send — the box goes into his basket, and it
 * is there the next time he opens the shop, on whichever telephone.
 */
class HandoffToCustomerTest extends TestCase
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

    private function basketFor(Product $box, int $quantity = 1, bool $rush = false): CartHandoff
    {
        return CartHandoff::fromCart([[
            'id' => 'built-by-the-shop',
            'product_id' => $box->id,
            'quantity' => $quantity,
            'photo_paths' => [],
            'custom_texts' => [],
        ]], $rush, 'Aygün, Instagram', null);
    }

    /** Another telephone: its own session, with nobody signed in on it. */
    private function anotherTelephone(): void
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        $this->assertGuest();
    }

    private function signIn(): void
    {
        $this->post(route('login'), ['login' => 'aygun@example.com', 'password' => 'chocolate8'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_it_lands_in_the_customers_basket_and_is_there_when_he_signs_in(): void
    {
        $box = $this->box();
        $user = $this->customer();
        $basket = $this->basketFor($box, 2);

        $added = $basket->giveTo($user);

        $this->assertSame(1, $added);
        $this->assertSame($user->id, $basket->fresh()->user_id);
        $this->assertNotNull($basket->fresh()->given_at);
        $this->assertSame(2, SavedCart::sole()->lines()[0]['quantity']);

        // He opens the shop on a telephone that knows nothing about any of this.
        $this->anotherTelephone();
        $this->assertSame(0, Cart::count());

        $this->signIn();

        $this->assertSame(2, Cart::count());
        $this->get(route('cart.index'))->assertOk()->assertSee('Love Story');
    }

    /**
     * The case the old marker got wrong: he is already signed in, on this
     * very telephone, when the shop puts the box in. It used to wait until
     * he signed in again, which he had no reason to do.
     */
    public function test_a_customer_already_signed_in_finds_it_on_his_next_page(): void
    {
        $box = $this->box();
        $second = $this->box('kinder');
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $second->id]);
        $this->assertSame(1, Cart::count());

        $this->basketFor($box)->giveTo($user->fresh());

        // Same session, same telephone, nothing signed in or out.
        $this->assertSame(2, Cart::count(), 'the given basket joins what he already had');
        $this->get(route('cart.index'))->assertOk()->assertSee('Love Story');
    }

    public function test_what_he_already_had_is_not_thrown_away(): void
    {
        $his = $this->box('kinder');
        $given = $this->box('love-story');
        $user = $this->customer();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $his->id, 'quantity' => 3]);
        $this->basketFor($given)->giveTo($user->fresh());

        $lines = SavedCart::sole()->lines();
        $this->assertCount(2, $lines);
        $this->assertEqualsCanonicalizing(
            [$his->id, $given->id],
            array_column($lines, 'product_id'),
        );
    }

    /** Each line gets its own id, so deleting one cannot reach back. */
    public function test_the_given_lines_are_copies(): void
    {
        $box = $this->box();
        $user = $this->customer();
        $basket = $this->basketFor($box);

        $basket->giveTo($user);

        $this->assertNotSame('built-by-the-shop', SavedCart::sole()->lines()[0]['id']);
        $this->assertSame('built-by-the-shop', $basket->fresh()->items[0]['id']);
    }

    public function test_a_rushed_basket_stays_rushed(): void
    {
        $box = $this->box();
        $user = $this->customer();

        $this->basketFor($box, 1, true)->giveTo($user);

        $this->assertTrue((bool) SavedCart::sole()->rush);
    }

    /** A basket cannot push somebody's own over the limit. */
    public function test_it_stops_at_the_limit(): void
    {
        $box = $this->box();
        $user = $this->customer();

        $full = [];
        for ($i = 0; $i < SavedCart::MOST; $i++) {
            $full[] = ['id' => 'his-' . $i, 'product_id' => $box->id, 'quantity' => 1,
                'photo_paths' => [], 'custom_texts' => []];
        }
        SavedCart::create(['user_id' => $user->id, 'items' => $full, 'rush' => false]);

        $added = $this->basketFor($box)->giveTo($user);

        $this->assertSame(0, $added);
        $this->assertCount(SavedCart::MOST, SavedCart::sole()->lines());
    }

    public function test_the_list_says_whose_it_is(): void
    {
        $box = $this->box();
        $user = $this->customer();
        $basket = $this->basketFor($box);

        $this->assertSame('Aygün, Instagram', $basket->forWhom());

        $basket->giveTo($user);

        $this->assertSame($user->name, $basket->fresh()->forWhom());
    }
}
