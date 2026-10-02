<?php

namespace Tests\Feature;

use App\Models\PhotoSlot;
use App\Models\Product;
use App\Support\DesignCopier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One box's design, laid onto another.
 *
 * The owner draws a family that differs in one setting — the shape of the map
 * window, or its colours — and drawing each from nothing is an evening's work
 * for a difference of two fields.
 */
class DesignCopierTest extends TestCase
{
    use RefreshDatabase;

    private function drawn(): Product
    {
        Storage::fake('public');

        $box = Product::create(['name' => 'Lokasiya', 'slug' => 'lokasiya', 'is_active' => true, 'price' => 24.9,
            'template_width' => 970, 'template_height' => 1904, 'box_color' => '#101010']);

        Storage::disk('public')->put($box->assetDirectory() . '/frame.webp', 'a picture');
        $box->layers()->create(['name' => 'çərçivə', 'image' => $box->assetDirectory() . '/frame.webp',
            'x' => -15, 'y' => 787, 'width' => 986, 'height' => 447, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);
        $box->shapes()->create(['kind' => 'rect', 'x' => 0, 'y' => 992, 'width' => 970, 'height' => 912,
            'rotation' => 0, 'fill' => '#ffffff', 'stroke_color' => '#000000', 'stroke_width' => 0,
            'radius' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->photoSlots()->create(['fill' => PhotoSlot::MAP, 'shape' => 'rectangle', 'map_style' => 'ink',
            'map_marker' => 'heart', 'map_zoom' => 15, 'map_choices' => 'zoom,marker', 'map_pin' => true,
            'x' => 100, 'y' => 100, 'width' => 770, 'height' => 800, 'rotation' => 0, 'sort_order' => 0]);
        foreach (['place' => 950, 'coords' => 1050] as $prints => $y) {
            $box->textSlots()->create(['auto' => $prints, 'label' => $prints, 'kind' => 'text', 'x' => 100, 'y' => $y,
                'max_width' => 770, 'font_size' => 48, 'color' => '#ffffff', 'align' => 'center',
                'tracking' => 240, 'sort_order' => $y]);
        }

        return $box;
    }

    private function blank(string $slug): Product
    {
        return Product::create(['name' => strtoupper($slug), 'slug' => $slug, 'is_active' => false, 'price' => 24.9]);
    }

    public function test_the_whole_design_comes_across(): void
    {
        $from = $this->drawn();
        $to = $this->blank('lokasiya-home');

        $this->assertTrue($to->isBlank());

        $made = DesignCopier::copy($from, $to);

        $this->assertSame(['layers' => 1, 'shapes' => 1, 'photos' => 1, 'texts' => 2], $made);
        $this->assertFalse($to->fresh()->isBlank());
        $this->assertSame('#101010', $to->fresh()->box_color);
        $this->assertSame(970, (int) $to->fresh()->template_width);

        $shape = $to->shapes()->first();
        $this->assertSame(992, (int) $shape->y);
        $this->assertSame('#ffffff', $shape->fill);

        /* What each caption prints is the one thing a copied design cannot
           get wrong: the box would be printed with the wrong words on it. */
        $this->assertSame(['place', 'coords'], $to->textSlots()->pluck('auto')->all());
        $this->assertSame(240, (int) $to->textSlots()->first()->tracking);
        $this->assertSame('#ffffff', $to->textSlots()->first()->color);
    }

    public function test_only_the_window_it_is_named_after_changes(): void
    {
        $from = $this->drawn();
        $to = $this->blank('lokasiya-daireli-green');

        DesignCopier::copy($from, $to, ['shape' => 'ellipse', 'map_style' => 'sea']);

        $slot = $to->photoSlots()->first();
        $this->assertSame('ellipse', $slot->shape);
        $this->assertSame('sea', $slot->map_style);

        /* Everything else about the window is the one the owner drew. */
        $this->assertSame('heart', $slot->map_marker);
        $this->assertSame(15, $slot->map_zoom);
        $this->assertSame('zoom,marker', $slot->map_choices);
        $this->assertSame(770, (int) $slot->width);
        $this->assertTrue((bool) $slot->map_pin);
    }

    public function test_each_box_gets_its_own_copy_of_the_pictures(): void
    {
        $from = $this->drawn();
        $to = $this->blank('lokasiya-invert');

        DesignCopier::copy($from, $to, ['map_style' => 'paper']);

        $image = $to->layers()->first()->image;
        $this->assertStringStartsWith($to->assetDirectory() . '/', $image);
        $this->assertNotSame($from->layers()->first()->image, $image);
        Storage::disk('public')->assertExists($image);

        /* Deleting one box empties its own folder and must leave the other
           whole — which is the reason the picture was copied at all. */
        $from->delete();
        Storage::disk('public')->assertExists($image);
    }

    public function test_copying_twice_does_not_double_the_design(): void
    {
        $from = $this->drawn();
        $to = $this->blank('lokasiya-rgb');

        DesignCopier::copy($from, $to, ['map_style' => 'colour']);
        DesignCopier::copy($from, $to, ['map_style' => 'colour']);

        $this->assertSame(1, $to->layers()->count());
        $this->assertSame(1, $to->shapes()->count());
        $this->assertSame(1, $to->photoSlots()->count());
        $this->assertSame(2, $to->textSlots()->count());
    }
}
