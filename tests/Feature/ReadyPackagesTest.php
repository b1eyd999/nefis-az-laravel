<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Wrapping;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A ready package: one price, nothing to decide.
 *
 * The owner puts one together, hangs it on a banner, and the customer
 * presses the button and lands on the design page with the bar, the paper,
 * the letter and the live photograph already settled. He adds his
 * photograph and orders.
 *
 * What these tests are really guarding is the money. The package has one
 * agreed price, so the parts must not be added up anywhere — not in the
 * basket, not on the order, not in the admin's totals — and a tampered form
 * must not be able to buy a dearer bar at the package's price.
 */
class ReadyPackagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function design(string $slug = 'yeni-il-qutusu', float $price = 10): Product
    {
        $box = Product::create(['name' => 'Yeni il qutusu', 'slug' => $slug, 'is_active' => true,
            'category' => 'sokolad', 'price' => $price,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->photoSlots()->create(['x' => 100, 'y' => 100, 'width' => 300, 'height' => 300,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);

        return $box;
    }

    /* A bar's price to the customer is the shop price plus the owner's
       markup, so it is read back from the model rather than assumed. */
    private function bar(string $name, float $base): Chocolate
    {
        return Chocolate::create(['name' => $name, 'base_price' => $base, 'is_active' => true]);
    }

    private function paper(string $name, float $price): Wrapping
    {
        return Wrapping::create(['name' => $name, 'is_active' => true, 'price' => $price,
            'pattern' => 'wrappings/x.webp']);
    }

    /** What the same things cost bought one at a time. */
    private function apart(Product $box, ?Chocolate $bar = null, ?Wrapping $paper = null): float
    {
        return round((float) $box->price + (float) ($bar?->price() ?? 0) + (float) ($paper?->price ?? 0), 2);
    }

    /**
     * A package three manat under what its parts come to.
     *
     * @return array{0: Bundle, 1: Product, 2: Chocolate, 3: Wrapping, 4: float}
     */
    private function package(?float $price = null, string $slug = 'yeni-il-qutusu'): array
    {
        $box = $this->design($slug);
        $bar = $this->bar('Milka', 4.00);
        $paper = $this->paper('Qırmızı kağız', 3.00);
        $apart = $this->apart($box, $bar, $paper);

        $bundle = Bundle::create([
            'name' => 'Yeni il dəsti', 'product_id' => $box->id,
            'chocolate_id' => $bar->id, 'wrapping_id' => $paper->id,
            'letter_on' => false, 'ar_on' => false,
            'price' => $price ?? round($apart - 3, 2), 'is_active' => true,
        ]);

        return [$bundle, $box, $bar, $paper, $apart];
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('mine.jpg', 900, 900);
    }

    // ---------------------------------------------------------- the page

    /** The link from the banner opens the design with the package on it. */
    public function test_the_banner_link_opens_the_design_with_the_package_settled(): void
    {
        [$bundle, $box, $bar, $paper] = $this->package();

        $page = $this->get('/products/' . $box->slug . '/customize?paket=' . $bundle->id)
            ->assertSuccessful();

        $page->assertSee('Yeni il dəsti', false);
        $page->assertSee($bar->name, false);
        $page->assertSee($paper->name, false);
        $page->assertSee('name="bundle_id" value="' . $bundle->id . '"', false);
        // The pickers are gone: there is nothing left to choose.
        $page->assertDontSee('name="chocolate_id" value="' . $bar->id . '" data-price', false);
    }

    /** Without the package the page is exactly what it always was. */
    public function test_without_the_package_the_page_is_unchanged(): void
    {
        [$bundle, $box, $bar] = $this->package();

        $page = $this->get('/products/' . $box->slug . '/customize')->assertSuccessful();
        $page->assertDontSee('name="bundle_id"', false);
        $page->assertSee('name="chocolate_id"', false);
    }

    /** A package switched off, or belonging to another design, is simply ignored. */
    public function test_a_package_that_is_not_offered_is_ignored(): void
    {
        [$bundle, $box] = $this->package();
        $other = $this->design('basqa-qutu');

        $bundle->forceFill(['is_active' => false])->save();
        $this->get('/products/' . $box->slug . '/customize?paket=' . $bundle->id)
            ->assertSuccessful()->assertDontSee('name="bundle_id"', false);

        $bundle->forceFill(['is_active' => true])->save();
        $this->get('/products/' . $other->slug . '/customize?paket=' . $bundle->id)
            ->assertSuccessful()->assertDontSee('name="bundle_id"', false);

        $this->get('/products/' . $box->slug . '/customize?paket=9999')
            ->assertSuccessful()->assertDontSee('name="bundle_id"', false);
    }

    // -------------------------------------------------------- the basket

    /** One price for the lot, not the parts added up. */
    public function test_the_basket_charges_the_package_price_and_not_the_sum(): void
    {
        [$bundle, $box, $bar, $paper, $apart] = $this->package();

        // Bought one at a time it would come to more; that is the whole point.
        $this->assertSame($apart, $bundle->fullPrice());
        $this->assertSame(round($apart - 3, 2), (float) $bundle->price);

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'bundle_id' => $bundle->id,
            'photos' => [$this->photo()], 'quantity' => 2,
        ])->assertSessionHasNoErrors();

        $line = Cart::items()[0];
        $this->assertSame((float) $bundle->price, Cart::unitPrice($line, $box));
        $this->assertLessThan($apart, Cart::unitPrice($line, $box));
        $this->assertSame($bundle->id, $line['bundle']['id']);
        // The parts are still on the line: the admin has to see what is in the box.
        $this->assertSame($bar->id, $line['chocolate']['id']);
        $this->assertSame($paper->id, $line['wrapping']['id']);
    }

    /**
     * And a form that says otherwise is not believed. The price belongs to
     * that exact bar and that exact paper.
     */
    public function test_a_tampered_form_cannot_swap_the_parts(): void
    {
        [$bundle, $box, $bar, $paper] = $this->package();
        $dearer = $this->bar('Lindt', 25.00);
        $dearerPaper = $this->paper('Qızılı kağız', 9.00);

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'bundle_id' => $bundle->id,
            'chocolate_id' => $dearer->id, 'wrapping_id' => $dearerPaper->id,
            'letter_on' => 1, 'ar_on' => 1,
            'photos' => [$this->photo()], 'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $line = Cart::items()[0];
        $this->assertSame($bar->id, $line['chocolate']['id'], 'the package decides, not the form');
        $this->assertSame($paper->id, $line['wrapping']['id']);
        $this->assertNull($line['letter'] ?? null, 'this package has no letter in it');
        $this->assertNull($line['ar'] ?? null);
        $this->assertSame((float) $bundle->price, Cart::unitPrice($line, $box));
    }

    /** A package nobody is offering cannot be bought by typing its number. */
    public function test_a_switched_off_package_cannot_be_bought(): void
    {
        [$bundle, $box] = $this->package();
        $bundle->forceFill(['is_active' => false])->save();

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'bundle_id' => $bundle->id,
            'photos' => [$this->photo()], 'quantity' => 1,
        ])->assertNotFound();

        $this->assertSame([], Cart::items());
    }

    /** Nor can a package be put against a design it was not made for. */
    public function test_a_package_cannot_be_moved_to_another_design(): void
    {
        [$bundle] = $this->package();
        $other = $this->design('basqa-qutu');
        $other->photoSlots()->create(['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);

        $this->post(route('cart.add'), [
            'product_id' => $other->id, 'bundle_id' => $bundle->id,
            'photos' => [$this->photo(), $this->photo()], 'quantity' => 1,
        ])->assertNotFound();
    }

    /**
     * A package whose bar was taken out of it still buys: the shop sells
     * bars, so the page would normally insist on one, and the package says
     * there is none.
     */
    public function test_a_package_without_a_bar_does_not_ask_for_one(): void
    {
        $box = $this->design();
        $this->bar('Milka', 4.00);        // the shop does sell bars
        $bundle = Bundle::create(['name' => 'Sadə dəst', 'product_id' => $box->id,
            'letter_on' => false, 'ar_on' => false, 'price' => 9.00, 'is_active' => true]);

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'bundle_id' => $bundle->id,
            'photos' => [$this->photo()], 'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(9.0, Cart::unitPrice(Cart::items()[0], $box));
        $this->assertNull(Cart::items()[0]['chocolate'] ?? null);
    }

    /** Without a package the shop still insists on a bar, as it always did. */
    public function test_without_a_package_a_bar_is_still_required(): void
    {
        $box = $this->design();
        $this->bar('Milka', 4.00);

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'photos' => [$this->photo()], 'quantity' => 1,
        ])->assertSessionHasErrors('chocolate_id');
    }

    // --------------------------------------------------------- the order

    /** The agreed price is frozen on the order and never moves again. */
    public function test_the_order_freezes_the_package_and_its_price(): void
    {
        [$bundle, $box] = $this->package();
        PaymentAccount::firstOrCreate(['number' => '4169738111111111'],
            ['type' => PaymentAccount::CARD, 'label' => 'K']);

        $this->actingAs(User::factory()->create())->post(route('cart.add'), [
            'product_id' => $box->id, 'bundle_id' => $bundle->id,
            'photos' => [$this->photo()], 'quantity' => 3,
        ])->assertSessionHasNoErrors();

        $this->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami 5',
        ])->assertSessionHasNoErrors();

        $order = Order::latest('id')->firstOrFail();
        $line = $order->items()->firstOrFail();

        $agreed = (float) $bundle->price;
        $this->assertSame($bundle->id, $line->bundle_id);
        $this->assertSame('Yeni il dəsti', $line->bundle_name);
        $this->assertSame($agreed, $line->bundle_price);
        $this->assertSame($agreed, $line->unitPrice(), 'the parts must not be added up on top');
        $this->assertSame(round($agreed * 3, 2), round($order->fresh()->load('items')->itemsTotal(), 2));

        // The owner reprices the package tomorrow; this order does not move.
        $bundle->forceFill(['price' => $agreed + 5, 'name' => 'Başqa ad'])->save();
        $this->assertSame($agreed, $line->fresh()->unitPrice());
        $this->assertSame('Yeni il dəsti', $line->fresh()->bundle_name);
    }

    /**
     * The three screens that fetch only the columns they need must fetch
     * bundle_price too, or unitPrice() falls back to the sum and the courier,
     * the money screen and the phone's order list all overcharge.
     */
    public function test_every_partial_select_asks_for_the_frozen_price(): void
    {
        foreach ([
            'app/Http/Controllers/CourierController.php',
            'app/Http/Controllers/Phone/MoneyController.php',
            'app/Http/Controllers/Phone/OrderController.php',
        ] as $file) {
            $src = file_get_contents(base_path($file));
            preg_match_all('/items:[a-z_,]+/', $src, $found);
            $this->assertNotEmpty($found[0], $file);
            foreach ($found[0] as $select) {
                $this->assertStringContainsString('bundle_price', $select,
                    $file . ' selects line prices without the package price: ' . $select);
            }
        }
    }

    /** An ordinary line is untouched by any of this. */
    public function test_an_ordinary_line_is_priced_exactly_as_before(): void
    {
        $box = $this->design('adi-qutu', 10);
        $bar = $this->bar('Milka', 4.00);
        $paper = $this->paper('Kağız', 3.00);

        $this->post(route('cart.add'), [
            'product_id' => $box->id, 'chocolate_id' => $bar->id, 'wrapping_id' => $paper->id,
            'photos' => [$this->photo()], 'quantity' => 1,
        ])->assertSessionHasNoErrors();

        $line = Cart::items()[0];
        $this->assertNull($line['bundle'] ?? null);
        $this->assertSame($this->apart($box, $bar, $paper), Cart::unitPrice($line, $box));
    }

    /** A package that saves nothing says nothing: no struck-through price. */
    public function test_a_package_that_saves_nothing_shows_no_old_price(): void
    {
        [$box, $bar, $paper] = [$this->design('a'), $this->bar('Milka', 4.00), $this->paper('K', 3.00)];
        $apart = $this->apart($box, $bar, $paper);

        $same = Bundle::create(['name' => 'Endirimsiz', 'product_id' => $box->id,
            'chocolate_id' => $bar->id, 'wrapping_id' => $paper->id,
            'price' => $apart, 'is_active' => true]);
        $this->assertNull($same->fullPrice(), 'nothing saved, so nothing to strike through');

        $same->forceFill(['price' => $apart - 2])->save();
        $this->assertSame($apart, $same->fresh()->load(['product', 'chocolate', 'wrapping'])->fullPrice());
    }
}
