<?php

namespace Tests\Feature;

use App\Mail\OrderMessage;
use App\Models\CourierPosition;
use App\Models\Order;
use App\Models\User;
use App\Support\Courier;
use App\Support\CourierTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The courier: an account of the shop's own, an order handed to him by name,
 * his own screen with nothing else on it, and — only while he says so — a dot
 * on the owner's map.
 */
class CourierTest extends TestCase
{
    use RefreshDatabase;

    private function courier(array $extra = []): User
    {
        return User::factory()->create($extra + ['role' => User::COURIER, 'name' => 'Ramil']);
    }

    private function order(?User $courier = null, array $extra = []): Order
    {
        // The factory's own address: two orders in one test must not collide.
        $customer = User::factory()->create();

        $order = Order::create($extra + [
            'user_id' => $customer->id,
            'status' => 'ready',
            'locale' => 'ru',
            'delivery_address' => 'Bakı, Nizami küç. 5',
            'contact_phone' => '0501234567',
        ]);

        if ($courier) {
            Courier::assign($order, $courier);
            $order->refresh();
        }

        return $order;
    }

    /* ---------------------------------------------------------------- roles */

    public function test_the_role_opens_the_couriers_screen_and_nothing_else_of_the_shop(): void
    {
        $courier = $this->courier();

        $this->actingAs($courier)->get('/kuryer')->assertOk();
        // Not staff, so neither the panel nor the owner's phone admin.
        $this->assertFalse($courier->isStaff());
        $this->actingAs($courier)->get('/admin/orders')->assertForbidden();
        $this->actingAs($courier)->get('/admin-phone')->assertForbidden();
        $this->actingAs($courier)->get('/admin-kuryerler/yerler')->assertForbidden();
    }

    public function test_nobody_else_gets_onto_it(): void
    {
        // Signed out first: actingAs() stays in force for the rest of a test.
        $this->get('/kuryer')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/kuryer')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get('/kuryer')->assertForbidden();
        // The owner may look, to see what his courier sees.
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))->get('/kuryer')->assertOk();
    }

    public function test_making_someone_a_courier_leaves_him_no_admin_rights(): void
    {
        $courier = $this->courier();

        $this->assertTrue($courier->isCourier());
        $this->assertFalse($courier->is_admin, 'the legacy column follows the role');
        $this->assertFalse($courier->isAdmin());
    }

    /* ------------------------------------------------------------ his orders */

    public function test_he_sees_the_orders_handed_to_him_and_only_those(): void
    {
        $mine = $this->order($courier = $this->courier());
        $someone_elses = $this->order($this->courier(['name' => 'Elvin']));
        $nobodys = $this->order();

        $this->actingAs($courier)->get('/kuryer')
            ->assertOk()
            ->assertSee('#'.$mine->id)
            ->assertDontSee('#'.$someone_elses->id)
            ->assertDontSee('#'.$nobodys->id);

        $this->actingAs($courier)->get('/kuryer/sifarish/'.$mine->id)->assertOk();
        $this->actingAs($courier)->get('/kuryer/sifarish/'.$someone_elses->id)->assertNotFound();
        $this->actingAs($courier)->get('/kuryer/sifarish/'.$nobodys->id)->assertNotFound();
    }

    public function test_he_cannot_touch_an_order_that_is_not_his(): void
    {
        $other = $this->order($this->courier(['name' => 'Elvin']));
        $courier = $this->courier();

        $this->actingAs($courier)->post('/kuryer/sifarish/'.$other->id.'/yolda')->assertNotFound();
        $this->actingAs($courier)->post('/kuryer/sifarish/'.$other->id.'/tehvil')->assertNotFound();
        $this->assertNull($other->fresh()->on_the_way_at);
        $this->assertSame('ready', $other->fresh()->status);
    }

    public function test_handing_it_over_writes_both_the_account_and_the_name_every_screen_reads(): void
    {
        Mail::fake();
        $courier = $this->courier(['email' => 'ramil@nefis.az']);
        $order = $this->order();

        Courier::assign($order, $courier);
        $order->refresh();

        $this->assertSame($courier->id, $order->courier_id);
        $this->assertSame('Ramil', $order->courier_name, 'the old column keeps every old screen working');
        $this->assertNotNull($order->courier_taken_at);
        $this->assertSame('Ramil', $order->courierLabel());
        // Nothing about the customer changed, so he was not written to.
        Mail::assertNotSent(OrderMessage::class);
    }

    public function test_taking_it_back_clears_the_trip_as_well(): void
    {
        $order = $this->order($courier = $this->courier());
        $order->forceFill(['on_the_way_at' => now()])->saveQuietly();

        Courier::assign($order->fresh(), null);
        $order->refresh();

        $this->assertNull($order->courier_id);
        $this->assertNull($order->on_the_way_at, 'nobody is on the way if nobody has it');
        $this->actingAs($courier)->get('/kuryer/sifarish/'.$order->id)->assertNotFound();
    }

    /* --------------------------------------------------------------- the trip */

    public function test_he_says_he_has_set_off_and_the_customer_is_not_written_to_by_it(): void
    {
        Mail::fake();
        $order = $this->order($courier = $this->courier());

        $this->actingAs($courier)->post('/kuryer/sifarish/'.$order->id.'/yolda')
            ->assertRedirect(route('courier.show', $order));

        $this->assertNotNull($order->fresh()->on_the_way_at);
        // Talking to customers stays the owner's own tap.
        Mail::assertNothingSent();
    }

    public function test_the_owners_tap_tells_the_customer_in_his_own_language(): void
    {
        Mail::fake();
        $order = $this->order($this->courier(['phone' => '0559998877']));

        $this->assertNull(Courier::tellCustomer($order));

        $this->assertNotNull($order->fresh()->on_the_way_at);
        Mail::assertSent(OrderMessage::class, function (OrderMessage $mail) use ($order) {
            return $mail->hasTo($order->user->email)
                && $mail->locale === 'ru'
                && str_contains($mail->body, 'Ramil')
                && str_contains($mail->body, '0559998877')
                && $mail->order->is($order);
        });
    }

    public function test_handing_over_finishes_the_order_the_way_every_other_screen_does(): void
    {
        $order = $this->order($courier = $this->courier());

        $this->actingAs($courier)->post('/kuryer/sifarish/'.$order->id.'/tehvil')
            ->assertRedirect(route('courier.index'));

        $this->assertSame('completed', $order->fresh()->status);
        // And it leaves his list.
        $this->actingAs($courier)->get('/kuryer')->assertOk()->assertDontSee('Alınacaq');
    }

    /* ------------------------------------------------------------ where he is */

    public function test_nothing_is_stored_until_he_switches_sharing_on(): void
    {
        $courier = $this->courier();

        $this->actingAs($courier)
            ->postJson('/kuryer/yer', ['lat' => 40.41, 'lng' => 49.86, 'accuracy' => 12])
            ->assertOk()
            ->assertJson(['sharing' => false]);

        $this->assertSame(0, CourierPosition::count());
    }

    public function test_with_it_on_his_readings_are_kept_and_the_window_is_pushed_along(): void
    {
        $courier = $this->courier();

        $this->actingAs($courier)->post('/kuryer/paylas', ['on' => 1])->assertRedirect();
        $this->assertTrue($courier->fresh()->isSharing());

        $this->actingAs($courier)
            ->postJson('/kuryer/yer', ['lat' => 40.41, 'lng' => 49.86, 'accuracy' => 9])
            ->assertOk()
            ->assertJson(['sharing' => true]);

        $point = CourierPosition::sole();
        $this->assertSame($courier->id, $point->user_id);
        $this->assertSame(40.41, $point->lat);
        $this->assertSame(49.86, $point->lng);
        $this->assertSame(9, $point->accuracy);
    }

    public function test_switching_it_off_takes_the_trail_with_it(): void
    {
        $courier = $this->courier();
        CourierTrail::start($courier);
        CourierTrail::record($courier, 40.41, 49.86);
        $this->assertSame(1, CourierPosition::count());

        $this->actingAs($courier)->post('/kuryer/paylas', ['on' => 0])->assertRedirect();

        $this->assertFalse($courier->fresh()->isSharing());
        $this->assertSame(0, CourierPosition::count(), 'where he was is nobody s business afterwards');
    }

    public function test_a_lapsed_window_stops_the_readings_without_anyone_switching_anything(): void
    {
        $courier = $this->courier();
        CourierTrail::start($courier);
        $courier->forceFill(['sharing_until' => now()->subMinute()])->save();

        $this->assertFalse($courier->fresh()->isSharing());
        $this->assertNull(CourierTrail::record($courier->fresh(), 40.41, 49.86));
        $this->assertSame(0, CourierPosition::count());
    }

    public function test_the_owners_map_shows_a_courier_who_is_out_and_what_he_carries(): void
    {
        $courier = $this->courier();
        $order = $this->order($courier);
        CourierTrail::start($courier);
        CourierTrail::record($courier->fresh(), 40.4100000, 49.8600000, 11);

        $answer = $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->getJson('/admin-kuryerler/yerler')->assertOk()->json('couriers');

        $this->assertCount(1, $answer);
        $this->assertSame('Ramil', $answer[0]['name']);
        $this->assertTrue($answer[0]['sharing']);
        $this->assertSame(40.41, $answer[0]['lat']);
        $this->assertCount(1, $answer[0]['orders']);
        $this->assertSame($order->id, $answer[0]['orders'][0]['id']);
    }

    public function test_a_delivered_order_takes_him_off_the_map(): void
    {
        $courier = $this->courier();
        $order = $this->order($courier);
        $order->forceFill(['status' => 'completed'])->save();

        $this->assertSame([], CourierTrail::map(), 'nothing to carry and not sharing');
    }

    public function test_the_owners_own_phone_never_becomes_a_courier_on_the_map(): void
    {
        $owner = User::factory()->create(['role' => User::ADMIN]);

        $this->actingAs($owner)->post('/kuryer/paylas', ['on' => 1])->assertRedirect();
        $this->actingAs($owner)->postJson('/kuryer/yer', ['lat' => 40.41, 'lng' => 49.86])->assertOk();

        $this->assertNull($owner->fresh()->sharing_until);
        $this->assertSame(0, CourierPosition::count());
    }

    /* ----------------------------------------------------------- the panel */

    public function test_the_owner_hands_an_order_over_from_the_orders_page(): void
    {
        $courier = $this->courier();
        $order = $this->order();

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/orders/'.$order->id.'/edit')
            ->assertOk()
            ->assertSee('Kuryerə ver');

        // And the map page is in the panel for him.
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/couriers')->assertOk()->assertSee('Kuryerlər');
    }

    public function test_he_hands_it_over_from_his_phone_too(): void
    {
        $courier = $this->courier();
        $order = $this->order();
        $owner = User::factory()->create(['role' => User::ADMIN]);

        $this->actingAs($owner)->get('/admin-phone/sifarish/'.$order->id)
            ->assertOk()->assertSee('Kuryeri yadda saxla');

        $this->actingAs($owner)
            ->post('/admin-phone/sifarish/'.$order->id.'/kuryer', ['courier_id' => $courier->id])
            ->assertRedirect();

        $this->assertSame($courier->id, $order->fresh()->courier_id);
    }

    public function test_the_phone_tap_tells_the_customer_as_well(): void
    {
        Mail::fake();
        $order = $this->order($this->courier());

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->post('/admin-phone/sifarish/'.$order->id.'/yolda')
            ->assertRedirect();

        $this->assertNotNull($order->fresh()->on_the_way_at);
        Mail::assertSent(OrderMessage::class);
    }

    public function test_a_courier_cannot_hand_orders_around_himself(): void
    {
        $courier = $this->courier();
        $order = $this->order();

        $this->actingAs($courier)
            ->post('/admin-phone/sifarish/'.$order->id.'/kuryer', ['courier_id' => $courier->id])
            ->assertForbidden();

        $this->assertNull($order->fresh()->courier_id);
    }
}
