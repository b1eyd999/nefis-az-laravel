<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Designs where the customer's face sits on someone else's body: the slot
 * says the head has to be cut out of the photo, and the page then brings in
 * the cutter that does it in the visitor's own browser.
 */
class FaceCutoutTest extends TestCase
{
    use RefreshDatabase;

    private function box(bool $cutout, string $shape = 'rectangle'): Product
    {
        $box = Product::create(['name' => 'Pampers', 'slug' => 'pampers', 'is_active' => true, 'price' => 4.90,
            'template_width' => 3508, 'template_height' => 2480]);
        $box->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 3508, 'height' => 2480, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $box->photoSlots()->create(['label' => 'Üz', 'x' => 2014, 'y' => 1225, 'width' => 428, 'height' => 488,
            'rotation' => 0, 'shape' => $shape, 'cutout' => $cutout, 'sort_order' => 0]);

        return $box;
    }

    public function test_a_cutout_slot_brings_the_cutter_to_the_page(): void
    {
        $this->box(true);

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertSee('js/face-cutout.js', false)
            // the face has to be found before it can be framed
            ->assertSee('face-api.min.js', false)
            ->assertSee('"cutout":true', false);
    }

    public function test_an_ordinary_slot_loads_nothing_extra(): void
    {
        $this->box(false);

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertDontSee('js/face-cutout.js', false)
            ->assertDontSee('face-api.min.js', false)
            ->assertSee('"cutout":false', false);
    }

    public function test_an_oval_slot_still_looks_for_a_face_without_cutting(): void
    {
        $this->box(false, 'ellipse');

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertSee('face-api.min.js', false)
            ->assertDontSee('js/face-cutout.js', false);
    }
}
