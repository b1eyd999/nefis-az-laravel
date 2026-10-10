<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * More or fewer of one line in the basket.
 *
 * Until now the basket printed «1 ədəd» and nothing else: wanting two of the
 * same box meant walking through the whole design again, photograph and all.
 */
class CartQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function box(float $price = 7): Product
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function lineId(): string
    {
        return Cart::items()[0]['id'];
    }

    public function test_the_basket_counts_up_and_down(): void
    {
        $box = $this->box(7);
        $this->post(route('cart.add'), ['product_id' => $box->id])->assertRedirect();

        $this->patch(route('cart.quantity', $this->lineId()), ['quantity' => 3])
            ->assertSessionHasNoErrors();
        $this->assertSame(3, Cart::items()[0]['quantity']);
        $this->assertSame(3, Cart::count());

        $this->patch(route('cart.quantity', $this->lineId()), ['quantity' => 1]);
        $this->assertSame(1, Cart::items()[0]['quantity']);
    }

    /** The page shows the stepper and what the line now costs. */
    public function test_the_page_shows_the_stepper_and_the_line_total(): void
    {
        $box = $this->box(7);
        $this->post(route('cart.add'), ['product_id' => $box->id]);
        $this->patch(route('cart.quantity', $this->lineId()), ['quantity' => 4]);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('class="qty"', false)
            ->assertSee('28 ₼')   // 4 × 7
            ->assertSee('7 ₼');   // and what one costs
    }

    /** A figure typed into the page cannot ask for four hundred boxes. */
    public function test_a_silly_number_is_refused(): void
    {
        $box = $this->box();
        $this->post(route('cart.add'), ['product_id' => $box->id]);
        $id = $this->lineId();

        $this->from(route('cart.index'))
            ->patch(route('cart.quantity', $id), ['quantity' => 400])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(1, Cart::items()[0]['quantity']);

        $this->from(route('cart.index'))
            ->patch(route('cart.quantity', $id), ['quantity' => 0])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(1, Cart::items()[0]['quantity']);

        // The ceiling itself is allowed.
        $this->patch(route('cart.quantity', $id), ['quantity' => Cart::MOST])
            ->assertSessionHasNoErrors();
        $this->assertSame(Cart::MOST, Cart::items()[0]['quantity']);
    }

    /** An id that is not in this basket changes nothing and does not fail. */
    public function test_an_unknown_line_is_left_alone(): void
    {
        $box = $this->box();
        $this->post(route('cart.add'), ['product_id' => $box->id]);

        $this->patch(route('cart.quantity', 'not-a-line'), ['quantity' => 5])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Cart::items()[0]['quantity']);
    }

    /** What a signed-in customer changes is kept where the session cannot lose it. */
    public function test_the_kept_basket_follows_the_number(): void
    {
        $user = User::factory()->create();
        $box = $this->box();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id]);
        $this->actingAs($user)->patch(route('cart.quantity', $this->lineId()), ['quantity' => 6]);

        $this->assertSame(6, $user->savedCart->items[0]['quantity']);
    }
}
