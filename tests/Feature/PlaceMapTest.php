<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\Setting;
use App\Models\TextSlot;
use App\Models\User;
use App\Support\MapImage;
use App\Support\StreetMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A place on a box: the streets around it, and what is printed underneath.
 *
 * A window of the design is told to hold a map instead of a photograph; the
 * customer then names the place and nothing is uploaded. What the order keeps
 * is the coordinates and the closeness — never a picture — so the workshop can
 * draw the same corner again at printing size whenever it likes.
 */
class PlaceMapTest extends TestCase
{
    use RefreshDatabase;

    private function box(array $slot = [], array $captions = ['place', 'coords']): Product
    {
        $box = Product::create(['name' => 'Lokasiya', 'slug' => 'lokasiya', 'is_active' => true, 'price' => 12,
            'template_width' => 1000, 'template_height' => 1600]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 1000, 'height' => 1600, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        $box->photoSlots()->create($slot + [
            'label' => null, 'fill' => PhotoSlot::MAP, 'map_style' => 'paper', 'map_marker' => 'star',
            'map_zoom' => 16, 'map_choices' => 'zoom,pin', 'map_pin' => true,
            'x' => 90, 'y' => 90, 'width' => 820, 'height' => 1000, 'rotation' => 0,
            'shape' => 'rectangle', 'sort_order' => 0,
        ]);

        foreach ($captions as $order => $auto) {
            $box->textSlots()->create([
                'label' => 'Yazı', 'kind' => TextSlot::KIND_TEXT, 'auto' => $auto, 'fixed' => false,
                'x' => 500, 'y' => 1200 + $order * 60, 'max_width' => 820, 'font_size' => 30,
                'color' => '#111', 'align' => 'center', 'rotation' => 0,
                'max_lines' => 1, 'max_length' => 60, 'sort_order' => $order,
            ]);
        }

        return $box;
    }

    /** Order the box and hand back the line the workshop will read. */
    private function order(Product $box, array $answers): \App\Models\OrderItem
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 1] + $answers)
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->firstOrFail()->items()->firstOrFail();
    }

    public function test_the_design_page_asks_for_a_place_and_not_for_a_photograph(): void
    {
        $box = $this->box();

        $html = $this->get(route('products.customize', $box->slug))->assertOk()
            ->assertSee('Lokasiya')
            ->assertSee('name="map_lat"', false)
            ->assertSee('name="map_lon"', false)
            ->assertSee('js/street-map.js', false)
            // The window fills itself, so no file is asked for at all.
            ->assertDontSee('name="photos[0]"', false)
            ->getContent();

        $this->assertStringContainsString('"fill":"map"', $html);
        $this->assertStringContainsString('"mapStyle":"paper"', $html);
        $this->assertStringContainsString('"mapMarker":"star"', $html);
        // What the licence asks in return for the streets.
        $this->assertStringContainsString('OpenStreetMap', $html);
    }

    public function test_only_the_switches_the_design_offers_are_shown(): void
    {
        $box = $this->box(['map_choices' => 'zoom']);

        $html = $this->get(route('products.customize', $box->slug))->assertOk()->getContent();

        $this->assertStringContainsString('id="map-zoom"', $html);
        // The switch itself is absent; only the script that would have read it
        // still names it, which is why the field, not the attribute, is checked.
        $this->assertStringNotContainsString('name="map_pin"', $html);
    }

    public function test_the_date_is_asked_for_only_when_a_caption_waits_for_one(): void
    {
        $plain = $this->box(captions: ['place']);
        $this->assertStringNotContainsString(
            'name="map_date"',
            $this->get(route('products.customize', $plain->slug))->getContent(),
        );

        $plain->textSlots()->create([
            'label' => 'Tarix', 'kind' => TextSlot::KIND_TEXT, 'auto' => 'date_long', 'fixed' => false,
            'x' => 500, 'y' => 1400, 'max_width' => 820, 'font_size' => 28, 'color' => '#111',
            'align' => 'center', 'rotation' => 0, 'max_lines' => 1, 'max_length' => 60, 'sort_order' => 9,
        ]);

        $this->assertStringContainsString(
            'name="map_date"',
            $this->get(route('products.customize', $plain->fresh()->slug))->getContent(),
        );
    }

    public function test_the_order_keeps_the_question_and_fills_the_captions_itself(): void
    {
        $box = $this->box();

        $item = $this->order($box, [
            'map_lat' => '41.0082', 'map_lon' => '28.9784',
            'map_place' => 'Sultanahmet, İstanbul',
            'map_zoom' => '17', 'map_pin' => '1',
            // Sent but never offered by this design, so it must be ignored.
            'custom_texts' => ['0' => 'əl ilə yazılmış', '1' => 'saxta koordinat'],
        ]);
        $spot = $item->street_map;

        $this->assertSame(41.0082, $spot['lat']);
        $this->assertSame(28.9784, $spot['lon']);
        $this->assertSame('Sultanahmet, İstanbul', $spot['place']);
        $this->assertSame(17, $spot['zoom']);
        // Frozen with the order, so recolouring the design later cannot
        // recolour what was bought.
        $this->assertSame('paper', $spot['style']);
        $this->assertSame('star', $spot['marker']);

        // The captions are worked out by the shop, never taken from the page.
        $this->assertSame([
            'Sultanahmet, İstanbul',
            \App\Support\Sky::coordinates(41.0082, 28.9784),
        ], $item->custom_texts);
    }

    public function test_a_switch_the_design_does_not_offer_keeps_the_owners_setting(): void
    {
        $box = $this->box(['map_choices' => '', 'map_zoom' => 13, 'map_pin' => false]);

        $spot = $this->order($box, [
            'map_lat' => '40.3777', 'map_lon' => '49.8920',
            'map_zoom' => '18', 'map_pin' => '1',
        ])->street_map;

        $this->assertSame(13, $spot['zoom']);
        $this->assertFalse($spot['pin']);
    }

    public function test_a_place_is_required_before_the_box_can_be_ordered(): void
    {
        $box = $this->box();

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), ['product_id' => $box->id, 'quantity' => 1])
            ->assertSessionHasErrors(['map_lat', 'map_lon']);
    }

    public function test_the_zoom_stays_inside_what_the_shop_offers(): void
    {
        $this->assertSame(StreetMap::ZOOM_MAX, StreetMap::zoom(99));
        $this->assertSame(StreetMap::ZOOM_MIN, StreetMap::zoom(1));
        $this->assertSame(14, StreetMap::zoom('14'));
    }

    public function test_the_streets_are_fetched_once_and_then_read_off_the_disk(): void
    {
        Storage::fake('local');
        MapImage::saveKey('test-key');
        Http::fake(['maps.geoapify.com/*' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png'])]);

        $first = MapImage::fetch(40.3777, 49.8920, 15, 'ink', 400, 500);
        $second = MapImage::fetch(40.3777, 49.8920, 15, 'ink', 400, 500);

        $this->assertSame($first, $second);
        Storage::disk('local')->assertExists($first);
        Http::assertSentCount(1);
    }

    public function test_the_key_never_reaches_the_browser(): void
    {
        Storage::fake('local');
        MapImage::saveKey('secret-key-123');
        Http::fake(['maps.geoapify.com/*' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png'])]);

        $answer = $this->get(route('place.image', ['lat' => 40.3777, 'lon' => 49.892, 'zoom' => 15, 'style' => 'ink']));

        $answer->assertOk();
        $this->assertSame('image/png', $answer->headers->get('Content-Type'));
        $this->assertStringNotContainsString('secret-key-123', $answer->getContent());
        // And it is not sitting in the database in the clear either.
        $this->assertNotSame('secret-key-123', Setting::get(Setting::GEOAPIFY_KEY));
        $this->assertSame('secret-key-123', MapImage::key());
    }

    public function test_the_owner_saves_the_key_on_the_settings_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->fillForm(['geoapify_key' => 'a-key-from-the-cabinet'])
            ->call('save')
            ->assertHasNoFormErrors();

        // Read back the way the shop reads it, through the cache and the
        // decryption, not straight off the row.
        $this->assertSame('a-key-from-the-cabinet', MapImage::key());
        $this->assertTrue(MapImage::ready());

        // And the page shows it again when it is opened next.
        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->assertFormSet(['geoapify_key' => 'a-key-from-the-cabinet']);
    }

    public function test_clearing_the_field_takes_the_key_away(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        MapImage::saveKey('something');
        $this->actingAs($admin);

        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->fillForm(['geoapify_key' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(MapImage::key());
    }

    public function test_a_whole_address_pasted_into_the_field_still_yields_the_key(): void
    {
        // Geoapify's own page hands the key over inside a sample address.
        MapImage::saveKey(
            'https://api.geoapify.com/v1/geocode/search?text=38%20Upper%20Montagu%20Street'
            . '&apiKey=63af0ea1402b4a8c9733381c941ac801'
        );
        $this->assertSame('63af0ea1402b4a8c9733381c941ac801', MapImage::key());

        // Quotes and stray spaces around a plain key go too.
        MapImage::saveKey('  "63af0ea1402b4a8c9733381c941ac801"  ');
        $this->assertSame('63af0ea1402b4a8c9733381c941ac801', MapImage::key());
    }

    public function test_the_probe_names_the_reason_rather_than_shrugging(): void
    {
        // Something that is neither a key nor an address with one in it.
        MapImage::saveKey('http://example.test/no/key/here');
        $this->assertSame('not-a-key', MapImage::probe()['why']);

        MapImage::saveKey('');
        $this->assertSame('no-key', MapImage::probe()['why']);

        MapImage::saveKey('a-wrong-key');
        Http::fake(['api.geoapify.com/*' => Http::response('{"message":"Invalid apiKey"}', 401)]);
        $refused = MapImage::probe();
        $this->assertSame('refused', $refused['why']);
        $this->assertStringContainsString('401', $refused['detail']);
        $this->assertStringContainsString('Invalid apiKey', $refused['detail']);
    }

    public function test_the_probe_says_so_when_the_key_works(): void
    {
        MapImage::saveKey('a-good-key');
        Http::fake(['api.geoapify.com/*' => Http::response(['results' => [['formatted' => 'Bakı, Azərbaycan']]])]);

        $good = MapImage::probe();
        $this->assertTrue($good['ok']);
        $this->assertSame('Bakı, Azərbaycan', $good['detail']);
    }

    public function test_the_window_stays_quiet_when_no_key_is_set(): void
    {
        MapImage::saveKey('');

        $this->get(route('place.image', ['lat' => 40.3777, 'lon' => 49.892]))->assertNotFound();
    }

    public function test_the_workshop_sheet_redraws_the_place_at_printing_size(): void
    {
        $box = $this->box();
        $staff = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $order = Order::create(['user_id' => $staff->id, 'status' => 'new', 'total' => 12,
            'customer_name' => 'A', 'customer_phone' => '+994500000000', 'customer_address' => 'Bakı', 'locale' => 'az']);
        $item = $order->items()->create([
            'product_id' => $box->id, 'quantity' => 1, 'price' => 12,
            'customer_photos' => [], 'custom_texts' => [],
            'street_map' => ['lat' => 40.3777, 'lon' => 49.892, 'zoom' => 16, 'pin' => true,
                'place' => 'Sahil', 'style' => 'paper', 'marker' => 'star', 'date' => null, 'time' => '', 'withTime' => false],
        ]);

        $this->actingAs($staff)->get(route('place.print', $item))->assertOk()
            ->assertSee('Sahil')
            ->assertSee(\App\Support\Sky::coordinates(40.3777, 49.892))
            ->assertSee('js/street-map.js', false)
            // Printed beside the streets, as the licence asks.
            ->assertSee('OpenStreetMap');
    }

    public function test_a_line_without_a_place_has_no_sheet(): void
    {
        $box = $this->box();
        $staff = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $order = Order::create(['user_id' => $staff->id, 'status' => 'new', 'total' => 12,
            'customer_name' => 'A', 'customer_phone' => '+994500000000', 'customer_address' => 'Bakı', 'locale' => 'az']);
        $item = $order->items()->create(['product_id' => $box->id, 'quantity' => 1, 'price' => 12,
            'customer_photos' => [], 'custom_texts' => []]);

        $this->actingAs($staff)->get(route('place.print', $item))->assertNotFound();
    }

    public function test_the_owner_can_build_a_map_window_in_the_editor(): void
    {
        $box = $this->box();
        $staff = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($staff)->postJson(route('box.save', $box), [
            'layers' => [], 'shapes' => [], 'texts' => [],
            'photos' => [[
                'label' => null, 'x' => 10, 'y' => 20, 'width' => 300, 'height' => 400, 'rotation' => 0,
                'shape' => 'home', 'fill' => 'map', 'map_style' => 'sea', 'map_marker' => 'pin',
                'map_zoom' => 13, 'map_choices' => 'zoom,nonsense', 'map_pin' => false,
            ]],
        ])->assertOk();

        $slot = $box->fresh()->photoSlots->first();
        $this->assertTrue($slot->isMap());
        $this->assertFalse($slot->needsUpload());
        $this->assertSame('home', $slot->shape);
        $this->assertSame('sea', $slot->map_style);
        $this->assertSame('pin', $slot->map_marker);
        $this->assertSame(13, $slot->map_zoom);
        // A word that is not one of ours is dropped rather than stored.
        $this->assertSame(['zoom'], $slot->mapChoices());
        $this->assertFalse((bool) $slot->map_pin);
    }

    public function test_an_unknown_kind_of_window_is_still_a_photograph(): void
    {
        $box = $this->box();
        $staff = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($staff)->postJson(route('box.save', $box), [
            'layers' => [], 'shapes' => [], 'texts' => [],
            'photos' => [[
                'label' => 'Foto', 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'rotation' => 0,
                'shape' => 'rectangle', 'fill' => 'moon',
            ]],
        ])->assertStatus(422);
    }
}
