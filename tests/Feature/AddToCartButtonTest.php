<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On a design with a photo window the "add to cart" button stays shut until
 * the picture is there. A shut button with nothing said about it reads as a
 * broken one, so a line under it says what is missing.
 */
class AddToCartButtonTest extends TestCase
{
    use RefreshDatabase;

    private function box(bool $withSlot): Product
    {
        $box = Product::create(['name' => 'Pampers', 'slug' => 'pampers', 'is_active' => true, 'price' => 4.90,
            'template_width' => 3508, 'template_height' => 2480]);
        $box->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 3508, 'height' => 2480, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);

        if ($withSlot) {
            $box->photoSlots()->create(['label' => 'Şəkil', 'x' => 700, 'y' => 700, 'width' => 400, 'height' => 400,
                'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false, 'sort_order' => 0]);
        }

        return $box;
    }

    public function test_a_design_that_needs_a_photo_says_why_the_button_is_shut(): void
    {
        $this->box(true);

        $page = $this->get(route('products.customize', 'pampers'))->assertOk();

        $page->assertSee('id="add-to-cart-btn"', false);
        $page->assertSee('disabled', false);
        $page->assertSee('Əvvəlcə şəklinizi yükləyin', false);
        // The browser's own refusal of the hidden required file input is answered by us.
        $page->assertSee("addEventListener('invalid'", false);
    }

    public function test_a_design_without_a_photo_window_opens_straight_away(): void
    {
        $this->box(false);

        $page = $this->get(route('products.customize', 'pampers'))->assertOk();

        $page->assertSee('id="add-to-cart-btn"', false);
        $page->assertDontSee('Əvvəlcə şəklinizi yükləyin', false);
        $page->assertDontSee('id="add-hint"', false);
    }

    public function test_the_line_is_read_in_the_visitors_language(): void
    {
        $this->box(true);

        $this->get(route('ru.products.customize', 'pampers'))->assertOk()
            ->assertSee('Сначала загрузите фотографию', false);
    }
}
