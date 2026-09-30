<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A design with nothing drawn on it: off the shelf by itself, marked in the
 * admin, and honest to a customer who still reaches it.
 */
class BlankDesignTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function product(bool $active = true): Product
    {
        return Product::create(['name' => 'Yeni poster', 'slug' => 'yeni-poster', 'is_active' => $active, 'price' => 12]);
    }

    public function test_a_design_with_nothing_on_it_knows_it_is_empty(): void
    {
        $product = $this->product();
        $this->assertTrue($product->isBlank());

        $product->shapes()->create([
            'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895, 'rotation' => 0,
            'fill' => '#111111', 'stroke_color' => null, 'stroke_width' => 0, 'radius' => 0,
            'opacity' => 100, 'placement' => 'below', 'sort_order' => 0,
        ]);

        $this->assertFalse($product->fresh()->isBlank());
    }

    public function test_saving_an_empty_design_in_the_editor_takes_it_off_the_site(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->postJson(route('box.save', $product->slug), [
            'layers' => [], 'shapes' => [], 'photos' => [], 'texts' => [],
        ])->assertOk()->assertJson(['ok' => true, 'blank' => true]);

        $this->assertFalse((bool) $product->fresh()->is_active);
    }

    public function test_a_design_that_has_artwork_is_left_switched_on(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->postJson(route('box.save', $product->slug), [
            'layers' => [], 'photos' => [], 'texts' => [],
            'shapes' => [[
                'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895, 'rotation' => 0,
                'fill' => '#0b0b0d', 'stroke_color' => null, 'stroke_width' => 0, 'radius' => 0,
                'opacity' => 100, 'placement' => 'below',
            ]],
        ])->assertOk()->assertJson(['blank' => false]);

        $this->assertTrue((bool) $product->fresh()->is_active);
    }

    public function test_a_customer_who_reaches_an_empty_design_is_told_it_is_being_made(): void
    {
        // The owner may switch an unfinished design on himself; the page is
        // then honest rather than a wrong-address error.
        $product = $this->product();

        $this->get(route('products.customize', $product->slug))
            ->assertNotFound()
            ->assertSee('Bu dizayn hazırlanır')
            ->assertSee('Yeni poster')
            ->assertSee('noindex', false);
    }

    public function test_a_switched_off_design_is_still_hidden_completely(): void
    {
        $this->get(route('products.customize', $this->product(active: false)->slug))
            ->assertNotFound()
            ->assertDontSee('Bu dizayn hazırlanır');
    }

    public function test_the_catalogue_card_leads_to_the_design_itself(): void
    {
        $product = $this->product();

        $html = $this->get(route('designs.index'))->assertOk()->getContent();

        // Before, an unfinished design's card led back to the list it sat in.
        $this->assertStringContainsString(route('products.customize', $product->slug), $html);
    }

    public function test_the_admin_list_shows_which_designs_are_empty(): void
    {
        $this->product();

        $this->actingAs($this->admin)->get('/admin/products')
            ->assertOk()
            ->assertSee('Boş');
    }
}
