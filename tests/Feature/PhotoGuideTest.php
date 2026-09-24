<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customers send whatever is on their phone, so the page shows what a usable
 * photo looks like before they pick one — drawn, not explained in a sentence.
 */
class PhotoGuideTest extends TestCase
{
    use RefreshDatabase;

    private function box(int $slots): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        for ($i = 0; $i < $slots; $i++) {
            $box->photoSlots()->create(['label' => null, 'x' => 0, 'y' => 200 * $i, 'width' => 400, 'height' => 500,
                'rotation' => 0, 'shape' => $i === 0 ? 'rectangle' : 'ellipse', 'sort_order' => $i]);
        }

        return $box;
    }

    public function test_every_photo_slot_shows_the_sketch_and_the_window_is_drawn_once(): void
    {
        $html = $this->get(route('products.customize', $this->box(2)->slug))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="photo-guide"'), 'a sketch under each slot');
        $this->assertSame(2, substr_count($html, 'class="pg-open" data-photo-guide'), 'each slot offers the examples');
        $this->assertSame(1, substr_count($html, 'id="photo-guide-modal"'), 'one window for the page');

        $this->assertStringContainsString('Şəkil necə olmalıdır?', $html);
        $this->assertStringContainsString('Belə olsun', $html);      // the shot to copy
        $this->assertStringContainsString('Yandan', $html);          // and the two that keep coming in
        $this->assertStringContainsString('Çox uzaqdan', $html);
    }

    public function test_a_design_without_photos_says_nothing_about_them(): void
    {
        $html = $this->get(route('products.customize', $this->box(0)->slug))->assertOk()->getContent();

        // The script that opens the window always travels with the page; what
        // must not be there is the sketch and the window themselves.
        $this->assertStringNotContainsString('class="photo-guide"', $html);
        $this->assertStringNotContainsString('id="photo-guide-modal"', $html);
    }
}
