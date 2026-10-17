<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliveryMethodResource\Pages\ListDeliveryMethods;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['phone' => '+994500000000']);
        DeliveryMethod::where('type', DeliveryMethod::DOOR)->update(['price' => 5]);
        DeliveryMethod::where('type', DeliveryMethod::POST)->update(['price' => 3.5]);
        DeliveryMethod::where('type', DeliveryMethod::METRO)->update(['price' => 2]);
    }

    private function method(string $type): DeliveryMethod
    {
        return DeliveryMethod::where('type', $type)->firstOrFail();
    }

    /** A cart holding one 4 ₼ box. */
    private function fillCart(): void
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $box->id])->assertSessionHasNoErrors();
    }

    public function test_checkout_offers_the_three_ways_with_their_prices(): void
    {
        $this->fillCart();

        $this->actingAs($this->customer)->get(route('checkout.index'))
            ->assertOk()
            ->assertSeeInOrder(['Qapıya çatdırılma', '5 ₼', 'Poçt ilə çatdırılma', '3.50 ₼', 'Metroya çatdırılma', '2 ₼'])
            ->assertSee('Yalnız Bakı daxilində')
            ->assertSee('Poçt şöbəsinin indeksi')
            ->assertSee('28 May');

        $this->actingAs($this->customer)->post(route('checkout.store'), ['contact_phone' => '1'])
            ->assertSessionHasErrors('delivery_method_id');
    }

    public function test_door_delivery_needs_the_address(): void
    {
        $this->fillCart();
        $door = $this->method(DeliveryMethod::DOOR);

        $this->actingAs($this->customer)->post(route('checkout.store'), ['delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1'])
            ->assertSessionHasErrors('delivery_address');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '+994 50 1', 'delivery_address' => 'Nəsimi r., Rəşid Behbudov 10',
        ])->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame(['door', 'Qapıya çatdırılma', 5.0, 'Nəsimi r., Rəşid Behbudov 10'],
            [$order->delivery_type, $order->delivery_name, $order->delivery_price, $order->delivery_address]);
        $this->assertSame(9.0, $order->total());
    }

    /**
     * This test used to assert the opposite, and the change is deliberate.
     *
     * It was written when the rule was «is the point inside Baku», and it
     * guarded a widening: the old box stopped at the built-up middle and
     * told a man in Qaradağ he was not in his own city. That was the right
     * fix for the question being asked then.
     *
     * The question changed. One courier cannot drive forty minutes up the
     * peninsula for one box, and an order from Buzovna showed the rule had
     * no opinion about distance at all. So the far settlements are out
     * again — not because they are not Baku, but because they are too far
     * to drive to. They are still served, by post and at a metro station.
     */
    public function test_the_door_is_driven_to_by_distance_not_by_the_city_limit(): void
    {
        // The city a courier crosses in a morning.
        $this->assertTrue(DeliveryMethod::reaches(40.3869, 49.8434), 'the middle of town');
        $this->assertTrue(DeliveryMethod::reaches(40.4650, 49.8200), 'Binəqədi, 7 km');
        $this->assertTrue(DeliveryMethod::reaches(40.3300, 49.7200), 'Lökbatan, 15 km');
        $this->assertTrue(DeliveryMethod::reaches(40.4675, 50.0467), 'the airport, 17 km');

        // Baku on paper, too far to drive for one box.
        $this->assertFalse(DeliveryMethod::reaches(40.5167, 50.1167), 'Buzovna, 24 km — the order that started this');
        $this->assertFalse(DeliveryMethod::reaches(40.4900, 50.1400), 'Mərdəkan, 25 km');
        $this->assertFalse(DeliveryMethod::reaches(40.35, 50.26), 'Zirə, 34 km');
        $this->assertFalse(DeliveryMethod::reaches(40.09, 49.41), 'Qobustan qəsəbəsi, Qaradağ');

        // And another city is still another city.
        $this->assertFalse(DeliveryMethod::reaches(40.6828, 46.3606), 'Gəncə');
        $this->assertFalse(DeliveryMethod::reaches(41.36, 49.22), 'Quba');
        $this->assertFalse(DeliveryMethod::reaches(38.75, 48.85), 'Lənkəran');
    }

    public function test_the_owner_can_move_the_limit_himself(): void
    {
        $door = $this->method(DeliveryMethod::DOOR);

        $this->assertFalse(DeliveryMethod::reaches(40.5167, 50.1167), 'Buzovna, at the default 20 km');

        $door->update(['options' => array_merge((array) $door->options, ['radius_km' => 30])]);
        $this->assertTrue(DeliveryMethod::reaches(40.5167, 50.1167), 'Buzovna, once he widens it to 30 km');

        $door->update(['options' => array_merge((array) $door->options, ['radius_km' => 10])]);
        $this->assertFalse(DeliveryMethod::reaches(40.3300, 49.7200), 'Lökbatan, once he narrows it to 10 km');
        $this->assertTrue(DeliveryMethod::reaches(40.3869, 49.8434), 'the middle of town, always');

        // Nonsense in the field falls back to the default rather than
        // refusing the whole city.
        $door->update(['options' => array_merge((array) $door->options, ['radius_km' => 0])]);
        $this->assertSame((float) DeliveryMethod::DOOR_RADIUS_KM, DeliveryMethod::doorRadiusKm());
    }

    public function test_the_distance_is_measured_the_way_a_map_measures_it(): void
    {
        // Against figures taken off a map, to a hundred metres.
        $this->assertEqualsWithDelta(24.3, DeliveryMethod::kmFromCentre(40.5167, 50.1167), 0.6, 'Buzovna');
        $this->assertEqualsWithDelta(16.5, DeliveryMethod::kmFromCentre(40.4675, 50.0467), 0.6, 'the airport');
        $this->assertEqualsWithDelta(0.0, DeliveryMethod::kmFromCentre(40.4093, 49.8671), 0.1, 'the centre itself');
    }

    public function test_the_door_delivery_point_comes_from_the_map_and_stays_in_baku(): void
    {
        $this->fillCart();
        $door = $this->method(DeliveryMethod::DOOR);

        $page = $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk();
        $page->assertSee('id="dlv-map"', false)->assertSee('data-google=""', false)->assertSee('js/map-picker.js');
        $page->assertSee('<meta name="referrer" content="strict-origin-when-cross-origin">', false);

        // Ganja is not Baku.
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Gəncə, Nizami küç. 1',
            'delivery_lat' => 40.6828, 'delivery_lng' => 46.3606,
        ])->assertSessionHasErrors('delivery_lng');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Rəşid Behbudov 10, mənzil 5',
            'delivery_lat' => 40.3869, 'delivery_lng' => 49.8434,
        ])->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame([40.3869, 49.8434], [$order->delivery_lat, $order->delivery_lng]);
        $this->assertSame('https://www.google.com/maps/search/?api=1&query=40.3869,49.8434', $order->mapUrl());
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/orders/' . $order->id . '/edit')->assertSee('Xəritədə aç');

        // With the owner's Google key the page uses it.
        $door->update(['options' => ['google_maps_key' => 'AIza-test-key']]);
        $this->actingAs($this->customer)->get(route('cart.index'));
        $this->assertSame('AIza-test-key', DeliveryMethod::googleMapsKey());
    }

    public function test_the_map_looks_addresses_up_through_the_site(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'nominatim.openstreetmap.org/reverse*' => \Illuminate\Support\Facades\Http::response([
                'name' => '', 'display_name' => 'long',
                'address' => ['road' => 'Rəşid Behbudov küçəsi', 'house_number' => '10', 'suburb' => 'Nəsimi rayonu', 'city' => 'Bakı'],
            ]),
            'nominatim.openstreetmap.org/search*' => \Illuminate\Support\Facades\Http::response([
                ['lat' => '40.3777', 'lon' => '49.8920', 'name' => 'Fəvvarələr meydanı', 'display_name' => 'Fəvvarələr meydanı, Bakı', 'address' => ['road' => 'Nizami küçəsi', 'city' => 'Bakı']],
            ]),
        ]);

        $this->getJson(route('map.reverse', ['lat' => 40.3869, 'lng' => 49.8434]))
            ->assertOk()->assertJson(['address' => 'Rəşid Behbudov küçəsi 10, Nəsimi rayonu, Bakı', 'outside' => false]);
        $this->getJson(route('map.reverse', ['lat' => 40.6828, 'lng' => 46.3606]))
            ->assertOk()->assertJson(['address' => null, 'outside' => true]);
        $this->getJson(route('map.search', ['q' => 'Fəvvarələr']))
            ->assertOk()->assertJsonPath('results.0.label', 'Fəvvarələr meydanı, Nizami küçəsi, Bakı')->assertJsonPath('results.0.lat', 40.3777);

        \Illuminate\Support\Facades\Http::assertSent(fn ($r) => str_contains($r->header('User-Agent')[0] ?? '', 'NefisShokoladEvi'));
    }

    public function test_post_delivery_needs_the_name_phone_and_post_office_index(): void
    {
        $this->fillCart();
        $post = $this->method(DeliveryMethod::POST);

        $this->actingAs($this->customer)->post(route('checkout.store'), ['delivery_method_id' => $post->id, 'contact_phone' => '1'])
            ->assertSessionHasErrors(['recipient_name', 'postal_index']);
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $post->id, 'contact_phone' => '1', 'recipient_name' => 'Aysel Məmmədova', 'postal_index' => 'Bakı',
        ])->assertSessionHasErrors('postal_index');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $post->id, 'contact_phone' => '+994 50 1', 'recipient_name' => 'Aysel Məmmədova', 'postal_index' => 'az 2001',
        ])->assertRedirect(route('orders.index'));

        $order = Order::firstOrFail();
        $this->assertSame(['post', 'Aysel Məmmədova', 'AZ2001', 3.5], [$order->delivery_type, $order->recipient_name, $order->postal_index, $order->delivery_price]);
        $this->assertSame('Aysel Məmmədova, poçt indeksi AZ2001', $order->deliverySummary());

        // The order page in the admin shows it all.
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/orders/' . $order->id . '/edit')
            ->assertOk()
            ->assertSee(['Poçt ilə çatdırılma', '3.50 ₼', '7.50 ₼']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(\App\Filament\Resources\OrderResource\Pages\EditOrder::class, ['record' => $order->getRouteKey()])
            ->assertFormSet(['recipient_name' => 'Aysel Məmmədova', 'postal_index' => 'AZ2001', 'contact_phone' => '+994 50 1']);
        $this->get('/admin/orders')->assertSee('Poçt ilə çatdırılma');
    }

    public function test_metro_delivery_needs_a_station_from_the_list(): void
    {
        $this->fillCart();
        $metro = $this->method(DeliveryMethod::METRO);

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $metro->id, 'contact_phone' => '1', 'metro_station' => 'Paris Nord',
        ])->assertSessionHasErrors('metro_station');

        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $metro->id, 'contact_phone' => '1', 'metro_station' => 'Gənclik',
        ])->assertRedirect(route('orders.index'));

        $this->assertSame('Metro: Gənclik', Order::firstOrFail()->deliverySummary());
        $this->actingAs($this->customer)->get(route('orders.index'))->assertSee('Metro: Gənclik')->assertSee('Cəmi: 6 ₼', false);
    }

    /**
     * A <select> draws its own list and nothing can be done to it, so the
     * station is picked from a list of ours. What the form sends is unchanged.
     */
    public function test_the_stations_are_offered_as_a_list_of_our_own(): void
    {
        $this->fillCart();
        $metro = $this->method(DeliveryMethod::METRO);
        $metro->update(['options' => ['stations' => ['Gənclik', 'Xocəsən']]]);

        $page = $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk();
        $page->assertSee('id="metro-pick"', false);
        $page->assertSee('data-v="Gənclik"', false);
        $page->assertSee('data-v="Xocəsən"', false);
        // The value still travels in a field the browser can refuse.
        $page->assertSee('class="pick-value" type="text" id="metro_station" name="metro_station"', false);
        $page->assertDontSee('<select id="metro_station"', false);
    }

    /** The owner's own list, and only it, reaches the customer. */
    public function test_the_owner_edits_the_stations_in_the_admin(): void
    {
        $this->fillCart();
        $metro = $this->method(DeliveryMethod::METRO);
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(\App\Filament\Resources\DeliveryMethodResource\Pages\EditDeliveryMethod::class,
            ['record' => $metro->getRouteKey()])
            ->fillForm(['options' => ['stations' => "Gənclik
Xocəsən

  28 May  "]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Gənclik', 'Xocəsən', '28 May'], $metro->fresh()->stations());

        $page = $this->actingAs($this->customer)->get(route('checkout.index'))->assertOk();
        $page->assertSee('data-v="28 May"', false);
        $page->assertDontSee('data-v="Nizami"', false);

        // And a station he took off the list is no longer accepted.
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $metro->id, 'contact_phone' => '1', 'metro_station' => 'Nizami',
        ])->assertSessionHasErrors('metro_station');
    }

    /** The list is also where it is edited, said on the table itself. */
    public function test_the_admin_table_says_where_the_stations_live(): void
    {
        $this->method(DeliveryMethod::METRO);

        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/delivery-methods')
            ->assertOk()
            ->assertSee('stansiya');
    }

    public function test_a_switched_off_way_is_neither_shown_nor_accepted(): void
    {
        $this->fillCart();
        $door = $this->method(DeliveryMethod::DOOR);
        $door->update(['is_active' => false]);

        $this->actingAs($this->customer)->get(route('checkout.index'))->assertDontSee('Qapıya çatdırılma');
        $this->actingAs($this->customer)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertSessionHasErrors('delivery_method_id');
    }

    public function test_the_owner_prices_the_ways_in_the_admin(): void
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/delivery-methods')->assertOk()->assertSee(['Qapıya çatdırılma', 'Poçt ilə çatdırılma', 'Metroya çatdırılma']);

        $door = $this->method(DeliveryMethod::DOOR);
        Livewire::test(ListDeliveryMethods::class)->call('updateTableColumnState', 'price', (string) $door->id, '6.50');
        $this->assertSame(6.5, $door->fresh()->price);

        $manager = User::factory()->create(['role' => User::MANAGER]);
        $this->actingAs($manager)->get('/admin/delivery-methods')->assertForbidden();
    }
}
