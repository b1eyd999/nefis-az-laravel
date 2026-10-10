<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What customers say once the box is in their hands.
 *
 * A review belongs to an order that was handed over — which is what makes
 * every review on the page a review by somebody who really bought
 * something — and nothing goes up until the owner has read it.
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function box(string $slug = 'test'): Product
    {
        $box = Product::create(['name' => 'Test ' . $slug, 'slug' => $slug, 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** An order of one design, handed over. */
    private function delivered(?User $user = null, ?Product $box = null): Order
    {
        $user ??= User::factory()->create(['name' => 'Aygün Həsənova']);
        $box ??= Product::first() ?? $this->box();

        $order = Order::create([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_type' => 'door',
            'delivery_name' => 'Qapıya',
            'delivery_price' => 0,
            'contact_phone' => '1',
            'delivery_address' => 'Bakı',
        ]);
        $order->items()->create([
            'product_id' => $box->id, 'product_name' => $box->name,
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1,
        ]);
        $order->forceFill(['status' => 'completed'])->save();

        return $order->fresh('items');
    }

    public function test_only_a_handed_over_order_may_be_written_about(): void
    {
        $this->box();
        $user = User::factory()->create();

        $open = Order::create(['user_id' => $user->id, 'status' => 'awaiting_payment',
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_type' => 'door', 'delivery_name' => 'Qapıya', 'delivery_price' => 0,
            'contact_phone' => '1', 'delivery_address' => 'Bakı']);

        $this->assertFalse(Review::invited($open, $user), 'nothing to say about a box he has not got');
        $this->actingAs($user)->get(route('orders.review', $open))->assertNotFound();

        $done = $this->delivered($user);
        $this->assertTrue(Review::invited($done, $user));
        $this->actingAs($user)->get(route('orders.review', $done))->assertOk();
    }

    /** Somebody else's order is not his to write about. */
    public function test_another_customers_order_is_out_of_reach(): void
    {
        $this->box();
        $order = $this->delivered();

        $this->actingAs(User::factory()->create())
            ->get(route('orders.review', $order))->assertNotFound();
        $this->actingAs(User::factory()->create())
            ->post(route('orders.review.store', $order), ['stars' => 5])->assertNotFound();
        $this->assertSame(0, Review::count());
    }

    public function test_he_writes_one_and_it_waits_for_the_owner(): void
    {
        Storage::fake('public');
        $box = $this->box();
        $user = User::factory()->create(['name' => 'Aygün Həsənova']);
        $order = $this->delivered($user, $box);

        $this->actingAs($user)->post(route('orders.review.store', $order), [
            'stars' => 5,
            'body' => 'Şəkil çox aydın çıxdı.',
            'photo' => UploadedFile::fake()->image('qutu.jpg', 900, 1200),
        ])->assertSessionHasNoErrors()->assertRedirect(route('orders.index'));

        $review = Review::firstOrFail();
        $this->assertSame(5, $review->stars);
        $this->assertSame($box->id, $review->product_id, 'one design, so it is about that design');
        $this->assertNotNull($review->photo);
        $this->assertFalse($review->isShown(), 'nothing goes up on its own');

        // And nothing of it is on the page yet.
        $this->get(route('reviews.index'))->assertOk()->assertDontSee('Şəkil çox aydın çıxdı.');

        // The owner lets it through, and then it is.
        $review->forceFill(['approved_at' => now()])->save();

        // Read as a visitor reads it, signed out: his own name in the header
        // would otherwise be the name the next assertion finds.
        auth()->logout();
        $this->assertSame('Aygün', $review->fresh()->who(), 'his first name, and no more');
        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('Şəkil çox aydın çıxdı.')
            ->assertSee('Aygün', false)
            ->assertDontSee('Həsənova', false);
    }

    /** One per order. */
    public function test_he_cannot_write_about_the_same_order_twice(): void
    {
        $this->box();
        $user = User::factory()->create();
        $order = $this->delivered($user);

        $this->actingAs($user)->post(route('orders.review.store', $order), ['stars' => 4]);
        $this->assertSame(1, Review::count());

        $this->actingAs($user)->get(route('orders.review', $order))->assertNotFound();
        $this->actingAs($user)->post(route('orders.review.store', $order), ['stars' => 1])->assertNotFound();
        $this->assertSame(1, Review::count());
    }

    public function test_the_stars_are_required_and_bounded(): void
    {
        $this->box();
        $user = User::factory()->create();
        $order = $this->delivered($user);

        $this->actingAs($user)->from(route('orders.review', $order))
            ->post(route('orders.review.store', $order), [])->assertSessionHasErrors('stars');
        $this->actingAs($user)->from(route('orders.review', $order))
            ->post(route('orders.review.store', $order), ['stars' => 9])->assertSessionHasErrors('stars');
        $this->actingAs($user)->from(route('orders.review', $order))
            ->post(route('orders.review.store', $order), ['stars' => 0])->assertSessionHasErrors('stars');

        $this->assertSame(0, Review::count());
    }

    /** A review of three different boxes says nothing about any one of them. */
    public function test_a_mixed_order_is_about_no_one_design(): void
    {
        $first = $this->box('bir');
        $second = $this->box('iki');
        $user = User::factory()->create();
        $order = $this->delivered($user, $first);
        $order->items()->create([
            'product_id' => $second->id, 'product_name' => $second->name,
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1,
        ]);

        $this->actingAs($user)->post(route('orders.review.store', $order->fresh('items')), ['stars' => 5]);

        $this->assertNull(Review::firstOrFail()->product_id);
    }

    /** The average, and what it is said to be. */
    public function test_the_shop_standing_counts_only_what_is_shown(): void
    {
        $box = $this->box();

        foreach ([5, 4, 2] as $stars) {
            $order = $this->delivered(User::factory()->create(), $box);
            Review::create([
                'order_id' => $order->id, 'user_id' => $order->user_id, 'product_id' => $box->id,
                'stars' => $stars, 'approved_at' => $stars === 2 ? null : now(),
            ]);
        }

        $standing = Review::standing();
        $this->assertSame(2, $standing['count'], 'the one still waiting is not counted');
        $this->assertSame(4.5, $standing['average']);

        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('4.5')
            ->assertSee('"@type":"AggregateRating"', false)
            ->assertSee('"reviewCount":2', false);
    }

    /** The design's own page shows its own stars, not the shop's. */
    public function test_the_design_page_shows_its_own_reviews(): void
    {
        $mine = $this->box('mine');
        $other = $this->box('other');

        $a = $this->delivered(User::factory()->create(), $mine);
        Review::create(['order_id' => $a->id, 'user_id' => $a->user_id, 'product_id' => $mine->id,
            'stars' => 5, 'body' => 'Bu dizayn əladır.', 'approved_at' => now()]);

        $b = $this->delivered(User::factory()->create(), $other);
        Review::create(['order_id' => $b->id, 'user_id' => $b->user_id, 'product_id' => $other->id,
            'stars' => 1, 'body' => 'O biri dizayn.', 'approved_at' => now()]);

        $this->get(route('products.customize', $mine->slug))->assertOk()
            ->assertSee('Bu dizayn əladır.')
            ->assertDontSee('O biri dizayn.');
    }

    /** The owner's answer is shown under the review. */
    public function test_the_shop_answers_under_the_review(): void
    {
        $box = $this->box();
        $order = $this->delivered(User::factory()->create(), $box);
        Review::create(['order_id' => $order->id, 'user_id' => $order->user_id, 'product_id' => $box->id,
            'stars' => 3, 'body' => 'Gec çatdı.', 'reply' => 'Üzr istəyirik, kuryeri dəyişdik.',
            'approved_at' => now()]);

        $this->get(route('reviews.index'))->assertOk()
            ->assertSee('Gec çatdı.')
            ->assertSee('Üzr istəyirik, kuryeri dəyişdik.');
    }

    /** Only the owner reads the queue. */
    public function test_only_the_owner_reaches_the_reviews(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/reviews')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/reviews')->assertOk();
    }
}
