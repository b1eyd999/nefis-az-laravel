<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * What order the shelf shows its boxes in.
 *
 * It used to be alphabetical, so the shelf never changed: whatever was drawn
 * last sank to the bottom and the same design led the catalogue for as long as
 * the shop existed. Now the newest comes first, unless the owner has dragged
 * the rows into an order of his own.
 */
class CatalogueOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        ProductCategory::create(['slug' => 'qutular', 'name' => 'Qutular', 'is_active' => true, 'sort_order' => 0]);
    }

    /** A box that is drawn on, so the catalogue counts it as ready. */
    private function design(string $name, string $slug, int $order = 0): Product
    {
        $box = Product::create(['name' => $name, 'slug' => $slug, 'category' => 'qutular',
            'is_active' => true, 'price' => 12, 'sort_order' => $order]);
        $box->shapes()->create(['kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
            'rotation' => 0, 'fill' => '#fff', 'stroke_color' => '#000', 'stroke_width' => 0,
            'radius' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    /** Where each design's link sits in the page, so order can be compared. */
    private function positions(string $body, array $slugs): array
    {
        $at = [];
        foreach ($slugs as $slug) {
            $at[$slug] = strpos($body, 'products/' . $slug . '/customize');
            $this->assertNotFalse($at[$slug], $slug . ' is missing from the catalogue');
        }

        return $at;
    }

    public function test_the_newest_design_leads_the_shelf(): void
    {
        $this->design('Alpen gold', 'alpen-gold');       // oldest, and first alphabetically
        $this->design('Zarafat', 'zarafat');
        $this->design('Yeni qutu', 'yeni-qutu');          // newest

        $at = $this->positions($this->get('/dizaynlar')->assertOk()->getContent(),
            ['yeni-qutu', 'zarafat', 'alpen-gold']);

        $this->assertLessThan($at['zarafat'], $at['yeni-qutu']);
        $this->assertLessThan($at['alpen-gold'], $at['zarafat']);
    }

    public function test_an_order_the_owner_set_beats_the_date(): void
    {
        $this->design('Alpen gold', 'alpen-gold', 1);     // dragged to the top
        $this->design('Yeni qutu', 'yeni-qutu', 2);

        $at = $this->positions($this->get('/dizaynlar')->assertOk()->getContent(),
            ['alpen-gold', 'yeni-qutu']);

        $this->assertLessThan($at['yeni-qutu'], $at['alpen-gold']);
    }

    public function test_a_design_added_after_the_dragging_opens_the_shelf_again(): void
    {
        /* Dragging writes a number on every row; a box made later keeps the
           default 0, which is lower than all of them. That is deliberate — a
           new box is the one the owner wants seen. */
        $this->design('Alpen gold', 'alpen-gold', 1);
        $this->design('Zarafat', 'zarafat', 2);
        $this->design('Lap yeni', 'lap-yeni');            // default sort_order

        $at = $this->positions($this->get('/dizaynlar')->assertOk()->getContent(),
            ['lap-yeni', 'alpen-gold', 'zarafat']);

        $this->assertLessThan($at['alpen-gold'], $at['lap-yeni']);
        $this->assertLessThan($at['zarafat'], $at['alpen-gold']);
    }
}
