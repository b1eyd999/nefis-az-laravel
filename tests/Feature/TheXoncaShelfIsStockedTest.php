<?php

namespace Tests\Feature;

use App\Models\Chocolate;
use App\Models\Product;
use App\Support\Cart;
use App\Support\Menu;
use App\Support\Xonca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The xonça shelf, which stood in the menu and held nothing.
 *
 * Thirteen designs are on it now, and they are ordinary designs — the real
 * basket, the real card payment, the real admin. What is not ordinary about
 * them is the arithmetic: a xonça is a trayful, so twenty is the fewest that
 * may be bought and three hundred the most, and the box holds a small sweet
 * of the shop's own rather than a bar off the shelf, so it costs 1.50 ₼ and
 * not 1.50 ₼ plus a bar.
 *
 * Both of those are figures the owner confirmed, and both would be wrong in
 * a way nobody notices until an order arrives — a tray of five, or a card
 * quoting a price the basket does not charge.
 */
class TheXoncaShelfIsStockedTest extends TestCase
{
    use RefreshDatabase;

    private function design(string $slug = 'xonca-kalliqrafiya'): Product
    {
        return Product::where('slug', $slug)->firstOrFail();
    }

    // ------------------------------------------------------------ the shelf

    public function test_the_shelf_holds_the_thirteen_designs(): void
    {
        $designs = Xonca::designs();

        $this->assertCount(13, $designs);
        $this->assertTrue(Xonca::ready());

        foreach ($designs as $design) {
            $this->assertSame('xonca', $design->category);
            $this->assertSame(1.5, (float) $design->price);
            $this->assertSame(20, $design->min_quantity);
            $this->assertSame(300, $design->max_quantity);
            $this->assertTrue($design->is_active);
            // Something is drawn on it, or it must not be for sale at all.
            $this->assertFalse($design->isBlank());
        }
    }

    public function test_the_page_shows_them_instead_of_its_coming_soon_line(): void
    {
        $page = $this->get('/xonca')->assertOk();

        $page->assertSee('Kalliqrafiya');
        $page->assertSee('Qızılı monoqram');
        $page->assertDontSee(Xonca::text('empty'));
    }

    /** The line on the bar stops promising once there is something behind it. */
    public function test_the_menu_line_no_longer_says_coming_soon(): void
    {
        $line = Menu::settings()['xonca'];

        $this->assertSame('', $line['badge']);
        $this->assertTrue($line['link']);
    }

    /**
     * Every design's artwork is really there, and every slot is on it.
     *
     * A slot a few hundred pixels past the edge still saves, still shows in
     * the admin, and prints the couple's names off the side of the box.
     */
    public function test_the_artwork_exists_and_the_slots_sit_on_it(): void
    {
        foreach (Xonca::designs() as $design) {
            $file = public_path((string) $design->template_image);
            $this->assertFileExists($file, $design->slug . ' has no printed face');

            $this->assertSame(969, $design->template_width);
            $this->assertSame(1895, $design->template_height);

            foreach ($design->textSlots as $slot) {
                $this->assertGreaterThan(0, $slot->x, $design->slug . ' / ' . $slot->label);
                $this->assertLessThan($design->template_width, $slot->x);
                $this->assertGreaterThan(0, $slot->y, $design->slug . ' / ' . $slot->label);
                $this->assertLessThan($design->template_height, $slot->y);
            }

            foreach ($design->photoSlots as $slot) {
                $this->assertGreaterThanOrEqual(0, $slot->x);
                $this->assertLessThanOrEqual($design->template_width, $slot->x + $slot->width);
                $this->assertLessThanOrEqual($design->template_height, $slot->y + $slot->height);
            }
        }
    }

    /**
     * The shelf shows boxes, not the blanks they are printed from.
     *
     * The artwork has the writing left out, so half the cards came out as
     * empty cream rectangles; each design carries a photograph of the
     * finished box for the catalogue instead.
     */
    public function test_every_card_shows_a_photograph_of_the_box(): void
    {
        foreach (Xonca::designs() as $design) {
            $card = (string) $design->catalogImage();

            $this->assertStringStartsWith('images/xonca/numune/', $card, $design->slug);
            $this->assertFileExists(public_path($card), $design->slug . ' has no card photograph');
            $this->assertNotSame($design->template_image, $card, $design->slug . ' shows its blank');
        }
    }

    /** The one design that asks for a photograph is the one that says so. */
    public function test_only_the_photograph_design_asks_for_a_photograph(): void
    {
        $withPhoto = Xonca::designs()->filter(fn (Product $p) => $p->photoSlots()->exists());

        $this->assertSame(['xonca-cutluk-sekli'], $withPhoto->pluck('slug')->all());
    }

    // ------------------------------------------------------------ the price

    public function test_a_xonca_box_is_priced_without_a_bar_from_the_shelf(): void
    {
        Chocolate::create(['name' => 'Milka 90 q', 'weight_g' => 90, 'base_price' => 3.64]);

        $xonca = $this->design();
        $this->assertFalse($xonca->needsChocolate());
        $this->assertSame(1.5, $xonca->priceFrom());

        // The page says the same, and so does the offer a search engine reads.
        $page = $this->get('/products/xonca-kalliqrafiya/customize')->assertOk();
        $page->assertSee('"price":"1.50"', false);
        // The picker itself, not the word: the page's script mentions the
        // field by name whether or not there is one to pick.
        $page->assertDontSee('id="choc-block"', false);
    }

    /** Every other design still has a bar in it, priced as it always was. */
    public function test_an_ordinary_design_still_carries_its_bar(): void
    {
        $bar = Chocolate::create(['name' => 'Milka 90 q', 'weight_g' => 90, 'base_price' => 3.00]);

        $ordinary = Product::create([
            'name' => 'Adi qutu', 'slug' => 'adi-qutu-' . uniqid(), 'category' => 'sokolad',
            'price' => 10, 'is_active' => true, 'template_image' => 'products/x.png',
        ]);

        $this->assertTrue($ordinary->needsChocolate());
        $this->assertGreaterThan(0, $bar->toCustomer()['price']);

        /* And its page still asks which bar goes in. Asserted on the page
           rather than on priceFrom(), which keeps the cheapest bar in a
           static for the life of the process - true of one request, and a
           trap in a suite where an earlier test priced a shop with no bars
           in it at all. */
        $this->get('/products/' . $ordinary->slug . '/customize')->assertOk()
            ->assertSee('id="choc-block"', false);
    }

    // ----------------------------------------------------------- the basket

    public function test_the_basket_takes_forty_of_one_design(): void
    {
        $xonca = $this->design();

        $this->post('/cart', [
            'product_id' => $xonca->id,
            'custom_texts' => ['Nişan', 'Aynur', 'Elvin', '12.12.2026'],
            'quantity' => 40,
        ])->assertRedirect();

        $items = Cart::items();
        $this->assertCount(1, $items);
        $this->assertSame(40, $items[0]['quantity']);
        $this->assertSame(60.0, round(Cart::unitPrice($items[0], $xonca) * 40, 2));
    }

    public function test_fewer_than_twenty_is_refused(): void
    {
        $xonca = $this->design();

        $this->post('/cart', [
            'product_id' => $xonca->id,
            'custom_texts' => ['Nişan', 'Aynur', 'Elvin', ''],
            'quantity' => 5,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame([], Cart::items());
    }

    public function test_more_than_three_hundred_is_refused(): void
    {
        $this->post('/cart', [
            'product_id' => $this->design()->id,
            'custom_texts' => ['Nişan', 'Aynur', 'Elvin', ''],
            'quantity' => 400,
        ])->assertSessionHasErrors('quantity');
    }

    /**
     * The twenty that stopped everyone else does not stop this one.
     *
     * `Cart::MOST` is twenty, and that is right for a gift box. A design that
     * names its own ceiling answers for itself.
     */
    public function test_the_shops_own_ceiling_does_not_apply_to_a_design_with_its_own(): void
    {
        $this->assertSame(20, Cart::most());
        $this->assertSame(300, Cart::most(null, $this->design()));
        $this->assertSame(20, Cart::least($this->design()));
        $this->assertSame(1, Cart::least());
    }

    /** Changing the number in the basket honours the same two figures. */
    public function test_the_basket_will_not_be_talked_below_twenty(): void
    {
        $xonca = $this->design();
        $this->post('/cart', [
            'product_id' => $xonca->id,
            'custom_texts' => ['Nişan', 'Aynur', 'Elvin', ''],
            'quantity' => 40,
        ])->assertRedirect();

        $id = Cart::items()[0]['id'];

        $this->patch('/cart/' . $id, ['quantity' => 3])->assertSessionHasErrors('quantity');
        $this->assertSame(40, Cart::items()[0]['quantity']);

        $this->patch('/cart/' . $id, ['quantity' => 120])->assertRedirect();
        $this->assertSame(120, Cart::items()[0]['quantity']);
    }

    /** Even called straight, the basket clamps rather than storing nonsense. */
    public function test_setting_the_number_directly_still_clamps(): void
    {
        $xonca = $this->design();
        Cart::add($xonca->id, [], ['Nişan', 'Aynur', 'Elvin', ''], 40);
        $id = Cart::items()[0]['id'];

        Cart::setQuantity($id, 2);
        $this->assertSame(20, Cart::items()[0]['quantity']);

        Cart::setQuantity($id, 5000);
        $this->assertSame(300, Cart::items()[0]['quantity']);
    }
}
