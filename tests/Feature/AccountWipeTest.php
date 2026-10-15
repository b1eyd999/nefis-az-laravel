<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\AccountWipe;
use App\Support\RecentSales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A customer closing his own account.
 *
 * The thing worth guarding here is the shape of the bargain: everything that
 * names him goes, and the order rows stay so the books still add up. Get
 * either half wrong and it is either a lie to the customer or a hole in the
 * accounts, and neither shows up until much later.
 */
class AccountWipeTest extends TestCase
{
    use RefreshDatabase;

    /** A finished order, so there is nothing left running to stop the wipe. */
    private function doneOrder(User $user, array $extra = []): Order
    {
        return Order::create($extra + [
            'user_id' => $user->id,
            'status' => 'completed',
            'locale' => 'az',
            'contact_phone' => '+994555555555',
            'recipient_name' => 'Aysel Məmmədova',
            'delivery_address' => 'Nizami küç. 203, mənzil 14',
            'note' => 'Zəng etməyin, qapını döyün',
            'delivery_price' => 5.00,
            'rush_fee' => 3.00,
        ]);
    }

    /* ------------------------------------------------------------- the wipe */

    public function test_the_person_goes_and_the_money_stays(): void
    {
        $user = User::factory()->create([
            'name' => 'Könül Məmmədova',
            'email' => 'konul@example.com',
            'phone' => '+994555555555',
        ]);
        $order = $this->doneOrder($user);

        $this->assertTrue(AccountWipe::run($user));

        $user->refresh();
        $order->refresh();

        $this->assertSame('', $user->name);
        $this->assertSame('silinmis-' . $user->id . '@nefis.invalid', $user->email);
        $this->assertNull($user->phone);
        $this->assertTrue($user->isScrubbed());

        // The order is still an order, and still says what it was worth.
        $this->assertNotNull(Order::find($order->id));
        $this->assertSame('completed', $order->status);
        $this->assertEquals(5.00, (float) $order->delivery_price);
        $this->assertEquals(3.00, (float) $order->rush_fee);
        $this->assertNotNull($order->created_at);

        // But no longer who it was for, nor where it went.
        $this->assertNull($order->contact_phone);
        $this->assertNull($order->recipient_name);
        $this->assertNull($order->delivery_address);
        $this->assertNull($order->note);
    }

    public function test_he_is_signed_out_everywhere_not_only_here(): void
    {
        $user = User::factory()->create();
        $this->doneOrder($user);

        foreach (['phone-session', 'laptop-session'] as $id) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $user->id, 'ip_address' => null,
                'user_agent' => null, 'payload' => '', 'last_activity' => time(),
            ]);
        }

        AccountWipe::run($user);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_he_can_open_a_new_account_on_the_same_number_afterwards(): void
    {
        $user = User::factory()->create(['phone' => '+994555555555']);
        $this->doneOrder($user);

        AccountWipe::run($user);

        // The guard that refuses a second account on one number must not find
        // the closed one, or he could never come back to the shop.
        $this->assertFalse(User::samePhone('+994555555555')->exists());
    }

    /* ------------------------------------------------------------- refusals */

    public function test_it_refuses_while_an_order_is_still_being_made(): void
    {
        $user = User::factory()->create();
        $this->doneOrder($user, ['status' => 'confirmed']);

        $this->assertNotNull(AccountWipe::refusal($user));
        $this->assertFalse(AccountWipe::run($user));
        $this->assertNull($user->refresh()->scrubbed_at);
    }

    public function test_it_refuses_the_shops_own_people_couriers_and_shareholders(): void
    {
        foreach ([
            ['role' => User::ADMIN],
            ['role' => User::MANAGER],
            ['role' => User::COURIER],
            ['profit_percent' => 25],
        ] as $what) {
            $user = User::factory()->create($what);
            $this->doneOrder($user);

            $this->assertNotNull(AccountWipe::refusal($user), json_encode($what));
            $this->assertFalse(AccountWipe::run($user), json_encode($what));
        }
    }

    /* ---------------------------------------------------------- his own page */

    public function test_it_is_on_his_page_and_asks_for_the_word(): void
    {
        $user = User::factory()->create();
        $this->doneOrder($user);

        $this->actingAs($user)->get('/hesabim')->assertOk()->assertSee('Hesabı silmək');

        // A wrong word changes nothing.
        $this->actingAs($user)->post('/hesabim/sil', ['confirm' => 'bəli'])
            ->assertSessionHasErrors('confirm');
        $this->assertNull($user->refresh()->scrubbed_at);

        // The right one closes the account and signs him out.
        $this->actingAs($user)->post('/hesabim/sil', ['confirm' => 'SİL'])
            ->assertRedirect('/');
        $this->assertNotNull($user->refresh()->scrubbed_at);
        $this->assertGuest();
    }

    public function test_a_closed_account_is_not_named_in_the_sales_ticker(): void
    {
        $user = User::factory()->create(['name' => 'Könül Məmmədova']);
        $product = Product::create([
            'name' => 'Test', 'slug' => 'ticker-test', 'is_active' => true, 'price' => 5,
        ]);
        $order = $this->doneOrder($user, ['created_at' => now()->subDay()]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Test',
            'quantity' => 1,
            'unit_price' => 5,
        ]);

        cache()->flush();
        $this->assertStringContainsString(
            'Könül',
            collect(RecentSales::all())->pluck('who')->implode(' ')
        );

        AccountWipe::run($user);

        cache()->flush();
        $this->assertSame([], RecentSales::all());
    }
}
