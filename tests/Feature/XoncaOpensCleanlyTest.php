<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\Menu;
use App\Support\Xonca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * «Xonça və nişan» — the day the owner opens it.
 *
 * Nothing is switched on by hand. One condition does it: an active design
 * filed under the xonça category. The moment there is one, the page fills,
 * Google is allowed in and the address joins the sitemap — all three
 * together, which is what this file is here to prove, so that opening it is
 * not a leap in the dark.
 *
 * Two things stay the owner's, in the admin's Menyu screen: the «Tezliklə»
 * badge beside the line, and whether the line is a door or just a word.
 */
class XoncaOpensCleanlyTest extends TestCase
{
    use RefreshDatabase;

    private function xoncaBox(string $slug = 'nisan-xoncasi'): Product
    {
        $box = Product::create([
            'name' => 'Nişan xonçası VOL 1', 'slug' => $slug, 'is_active' => true,
            'category' => Xonca::CATEGORY, 'price' => 1.2,
            'template_width' => 500, 'template_height' => 900,
        ]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp',
            'x' => 0, 'y' => 0, 'width' => 500, 'height' => 900, 'rotation' => 0,
            'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** Before the first design: a promise, and nothing Google can file. */
    public function test_closed_it_says_so_and_keeps_itself_out_of_the_index(): void
    {
        $this->assertFalse(Xonca::ready());

        $this->get('/xonca')->assertSuccessful()->assertSee('noindex', false);
        $this->get('/sitemap.xml')->assertSuccessful()->assertDontSee('/xonca');
    }

    /** The first design opens the page, the index and the sitemap together. */
    public function test_one_design_opens_all_three_at_once(): void
    {
        $box = $this->xoncaBox();

        $this->assertTrue(Xonca::ready());

        $page = $this->get('/xonca')->assertSuccessful();
        $page->assertSee($box->name, false);
        $page->assertDontSee('noindex', false);

        $this->get('/sitemap.xml')->assertSuccessful()->assertSee('/xonca</loc>', false);
    }

    /** And in all three languages, each pointing at itself. */
    public function test_it_opens_in_every_language(): void
    {
        $box = $this->xoncaBox();

        foreach (['/xonca' => 'az', '/ru/xonca' => 'ru', '/en/xonca' => 'en'] as $url => $lang) {
            $answer = $this->get($url)->assertSuccessful();
            $answer->assertSee($box->name, false);
            $answer->assertSee('<html lang="' . $lang . '"', false);
            $answer->assertDontSee('noindex', false);
        }
    }

    /** A design on that page is a design like any other: it can be bought. */
    public function test_a_xonca_design_can_be_customised_and_put_in_the_basket(): void
    {
        $box = $this->xoncaBox();

        $this->get('/products/' . $box->slug . '/customize')->assertSuccessful();

        $this->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 12])
            ->assertSessionHasNoErrors();

        $this->get('/cart')->assertSuccessful()->assertSee($box->name, false);
    }

    /**
     * The line in the menu is the owner's to open. Until he does it is a
     * word and not a door — which is deliberate, so that the «Tezliklə» he
     * sees is not a link that leads to an empty shelf.
     */
    public function test_the_menu_line_is_the_owners_switch(): void
    {
        $shut = Menu::settings()['xonca'];
        $this->assertSame('Tezliklə', $shut['badge']);
        $this->assertFalse($shut['link'], 'closed, the line goes nowhere');
        $this->assertSame('top', $shut['place'], 'and it stands on the bar, not in the drop-down');

        $this->xoncaBox();

        // What the owner does in Menyu: clear the badge, make it a door.
        $rows = [];
        foreach (Menu::settings() as $key => $row) {
            $rows[] = $key === 'xonca'
                ? ['key' => $key] + ['on' => true, 'badge' => '', 'place' => 'top', 'link' => true]
                : ['key' => $key] + $row;
        }
        Menu::save($rows);

        $open = Menu::settings()['xonca'];
        $this->assertSame('', $open['badge']);
        $this->assertTrue($open['link']);

        // And the header now carries a real link to it.
        $this->get('/')->assertSuccessful()->assertSee('/xonca"', false);
    }

    /** A design that is not switched on must not open the page by itself. */
    public function test_a_hidden_design_does_not_open_it(): void
    {
        $this->xoncaBox()->forceFill(['is_active' => false])->save();

        $this->assertFalse(Xonca::ready());
        $this->get('/xonca')->assertSuccessful()->assertSee('noindex', false);
    }

    /**
     * What the catalogue does with a xonça box, written down as it is.
     *
     * /dizaynlar lists every active design, grouped by its shelf, with a
     * block at the end for anything filed under a shelf that is not there.
     * A xonça box is active, so it appears there too — on its own page AND
     * in the catalogue, a tray chocolate at 1.20 ₼ standing beside the gift
     * boxes at eight.
     *
     * That may be exactly right, or it may not; it is the owner's call, not
     * a fault. It is pinned here so that the day it changes, it changes on
     * purpose.
     */
    public function test_a_xonca_box_shows_in_the_catalogue_too(): void
    {
        $plain = Product::create(['name' => 'Adi qutu', 'slug' => 'adi-qutu', 'is_active' => true,
            'category' => 'sokolad', 'price' => 7.5, 'template_width' => 969, 'template_height' => 1895]);
        $this->xoncaBox();

        $designs = $this->get('/dizaynlar')->assertSuccessful();
        $designs->assertSee($plain->name, false);
        $designs->assertSee('Nişan xonçası VOL 1', false);

        // Its own page shows it and nothing else.
        $this->assertSame(['nisan-xoncasi'], Xonca::designs()->pluck('slug')->all());
    }

    /**
     * The owner is the only one who can switch a design on, and the page
     * follows him rather than any visitor.
     */
    public function test_a_visitor_cannot_open_it(): void
    {
        $box = $this->xoncaBox();
        $box->forceFill(['is_active' => false])->save();

        $this->actingAs(User::factory()->create())->get('/xonca')
            ->assertSuccessful()->assertSee('noindex', false);
    }
}
