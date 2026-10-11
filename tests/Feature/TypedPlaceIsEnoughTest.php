<?php

namespace Tests\Feature;

use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\Setting;
use App\Support\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A «Lokasiya» box is ordered by naming the place, not by tapping a dropdown.
 *
 * The map's suggestion list was the only thing that ever filled map_lat and
 * map_lon, and both were required — so a customer who typed «Sahil» and
 * pressed his keyboard's done key was refused by two hidden fields, while
 * the line under the button told him to upload a photograph this design does
 * not have. These are the priciest boxes in the shop.
 */
class TypedPlaceIsEnoughTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::GEOAPIFY_KEY, \Illuminate\Support\Facades\Crypt::encryptString('test-key'));
        Http::preventStrayRequests();
        Http::fake(['api.geoapify.com/*' => Http::response([
            'results' => [[
                'formatted' => 'Sahil metrosu, Bakı',
                'address_line1' => 'Sahil',
                'lat' => 40.3725,
                'lon' => 49.8419,
            ]],
        ])]);
    }

    /** A design whose one window is a street map. */
    private function mapBox(): Product
    {
        $box = Product::create(['name' => 'Lokasiya', 'slug' => 'lokasiya', 'is_active' => true,
            'price' => 24.90, 'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);
        $box->photoSlots()->create(['x' => 0, 'y' => 0, 'width' => 969, 'height' => 1200,
            'shape' => 'rect', 'sort_order' => 0, 'fill' => PhotoSlot::MAP]);

        return $box->fresh(['photoSlots', 'textSlots']);
    }

    public function test_the_place_he_typed_is_enough(): void
    {
        $box = $this->mapBox();

        $this->post(route('cart.add'), [
            'product_id' => $box->id,
            'map_place' => 'Sahil',       // typed, never picked from the list
        ])->assertSessionHasNoErrors()->assertRedirect();

        $item = Cart::items()[0];
        $this->assertSame(40.3725, $item['spot']['lat'], 'the shop looked the place up itself');
        $this->assertSame(49.8419, $item['spot']['lon']);
        $this->assertSame('Sahil', $item['spot']['place'], 'and his own words stay on the box');
    }

    /** A point tapped on the map still works, and is not looked up again. */
    public function test_a_tapped_point_is_taken_as_it_is(): void
    {
        $box = $this->mapBox();

        $this->post(route('cart.add'), [
            'product_id' => $box->id,
            'map_place' => 'Nizami küçəsi',
            'map_lat' => 40.4000,
            'map_lon' => 49.8500,
        ])->assertSessionHasNoErrors();

        $item = Cart::items()[0];
        $this->assertSame(40.4, $item['spot']['lat']);
        $this->assertSame('Nizami küçəsi', $item['spot']['place']);
        Http::assertNothingSent();
    }

    /** Nothing at all is refused — but against the field he can see. */
    public function test_naming_no_place_is_refused_against_the_place_field(): void
    {
        $box = $this->mapBox();

        $this->from(route('products.customize', $box->slug))
            ->post(route('cart.add'), ['product_id' => $box->id])
            ->assertSessionHasErrors('map_place');

        $this->assertSame([], Cart::items());
    }

    /** A place the map cannot find is said so, beside the box he typed in. */
    public function test_a_place_that_cannot_be_found_is_said_so(): void
    {
        /* Http::fake MERGES rather than replaces, so the setUp stub would
           still answer. The geocoder finding nothing is the same thing to
           this code as the shop having no map key at all, which is a real
           state of the admin — so that is what is tested. */
        Setting::put(Setting::GEOAPIFY_KEY, '');
        $box = $this->mapBox();

        $this->from(route('products.customize', $box->slug))
            ->post(route('cart.add'), ['product_id' => $box->id, 'map_place' => 'qwertyuiop'])
            ->assertSessionHasErrors('map_place');

        $this->assertSame([], Cart::items());
    }

    /** The box he types into is the one that is sent. */
    public function test_the_page_sends_the_box_he_types_into(): void
    {
        $box = $this->mapBox();

        $this->get(route('products.customize', $box->slug))->assertOk()
            ->assertSee('id="map-search" name="map_place"', false)
            ->assertDontSee('name="map_place" id="map-place"', false);
    }
}
