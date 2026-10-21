<?php

namespace Tests\Feature;

use App\Filament\Pages\Samira;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\SamiraKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Samira, the assistant on the owner's computer, reads the shop through
 * /api/samira/* with a key the site made — and only reads.
 *
 * What is pinned here is the door (401 for anything but the key, the key
 * kept only as a hash, a throttle, no CORS, nothing cached) and the rule the
 * feed keeps: a first name and what was in the box, never a telephone, an
 * address, an e-mail or a surname.
 */
class SamiraReadsTheShopTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+994 55 123 45 67';

    private const ADDRESS = 'Nizami küçəsi 12, mənzil 34';

    private const EMAIL = 'aysel.secret';

    private const SURNAME = 'Məmmədova';

    private int $people = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(Carbon::parse('2026-10-21 15:00:00', 'Asia/Baku'));
    }

    private function key(): string
    {
        return SamiraKey::make();
    }

    private function feed(string $key, array $query = [], array $headers = [])
    {
        return $this->withHeaders($headers + ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'])
            ->get('/api/samira/feed' . ($query ? '?' . http_build_query($query) : ''));
    }

    private function box(): Product
    {
        return Product::firstWhere('slug', 'sevgi') ?? Product::create([
            'name' => 'Sevgi qutusu', 'slug' => 'sevgi', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895,
        ]);
    }

    private function order(array $over = []): Order
    {
        $n = ++$this->people;
        $user = User::factory()->create([
            'name' => 'Aysel ' . self::SURNAME,
            'email' => self::EMAIL . $n . '@example.com',
            'phone' => '+99455123456' . $n,
        ]);

        $order = Order::create(array_replace([
            'user_id' => $user->id,
            'status' => 'confirmed',
            'locale' => 'az',
            'delivery_type' => 'door',
            'delivery_name' => 'Qapıya',
            'delivery_price' => 5,
            'delivery_date' => '2026-10-23',
            'delivery_slot' => '14:00–18:00',
            'contact_phone' => self::PHONE,
            'delivery_address' => self::ADDRESS,
            'recipient_name' => 'Leyla ' . self::SURNAME,
            'metro_station' => 'Gənclik',
            'note' => 'Qapının kodu 4321',
            'payment_method' => 'card',
            'payment_confirmed_at' => now()->subHour(),
        ], $over));

        $order->items()->create([
            'product_id' => $this->box()->id, 'product_name' => 'Sevgi qutusu',
            'customer_photos' => ['cart-photos/secret-face.jpg'], 'custom_texts' => ['Səni sevirəm, Leyla'],
            'quantity' => 2, 'price' => 20, 'chocolate_name' => 'Milka', 'chocolate_price' => 3,
        ]);

        return $order->fresh(['items']);
    }

    // ---- the door ----

    public function test_no_key_is_a_401(): void
    {
        $this->key();

        $this->getJson('/api/samira/feed')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'no_key'])
            ->assertHeader('WWW-Authenticate', 'Bearer realm="samira"');

        $this->getJson('/api/samira/order/1')->assertStatus(401);
    }

    public function test_a_wrong_key_is_a_401(): void
    {
        $key = $this->key();

        $this->feed('nefis_' . str_repeat('0', 64))->assertStatus(401)->assertExactJson(['error' => 'bad_key']);
        $this->feed(substr($key, 0, -1))->assertStatus(401);
        $this->feed($key . 'x')->assertStatus(401);
        $this->feed(str_repeat('a', 5000))->assertStatus(401);

        // The key in the address is not the key: it would end up in logs.
        $this->flushHeaders();
        $this->getJson('/api/samira/feed?key=' . $key)->assertStatus(401);
        $this->getJson('/api/samira/feed?api_token=' . $key)->assertStatus(401);

        // Nor is any other way of writing the header.
        $this->withHeaders(['Authorization' => 'Basic ' . base64_encode('samira:' . $key)])
            ->getJson('/api/samira/feed')->assertStatus(401);
    }

    public function test_with_no_key_made_every_call_is_refused(): void
    {
        // Nothing made yet: a blank stored hash must never match anything,
        // the hash of an empty string included.
        $this->assertFalse(SamiraKey::exists());
        $this->feed('')->assertStatus(401);
        $this->feed(hash('sha256', ''))->assertStatus(401);
        $this->feed('nefis_anything')->assertStatus(401);
    }

    public function test_the_right_key_reads_the_feed(): void
    {
        $key = $this->key();

        $this->feed($key)
            ->assertOk()
            ->assertJsonStructure([
                'site', 'now', 'since', 'timezone', 'currency',
                'new_orders', 'new_receipts',
                'today' => ['date', 'orders', 'sum', 'deliveries', 'awaiting_payment', 'awaiting_payment_sum'],
                'waiting' => ['count', 'by_status', 'late', 'adjustments_to_check', 'orders'],
                'more',
            ])
            ->assertJsonPath('site', 'nefis.az')
            ->assertJsonPath('timezone', 'Asia/Baku')
            ->assertJsonPath('now', now()->getTimestamp());
    }

    public function test_only_a_hash_of_the_key_is_kept(): void
    {
        $key = $this->key();

        $this->assertStringStartsWith(SamiraKey::PREFIX, $key);
        $this->assertSame(70, strlen($key));
        $this->assertSame(hash('sha256', $key), Setting::get(Setting::SAMIRA_KEY_HASH));

        // Nowhere in the settings table, in any column.
        $everything = DB::table('settings')->get()->toJson();
        $this->assertStringNotContainsString($key, $everything);
        $this->assertStringNotContainsString(substr($key, strlen(SamiraKey::PREFIX)), $everything);

        // Two keys are never the same key.
        $this->assertNotSame($key, $this->key());
    }

    public function test_a_new_key_finishes_the_old_one_and_off_finishes_both(): void
    {
        $old = $this->key();
        $new = $this->key();

        $this->feed($old)->assertStatus(401);
        $this->feed($new)->assertOk();

        SamiraKey::off();

        $this->feed($new)->assertStatus(401);
        $this->assertFalse(SamiraKey::exists());
    }

    public function test_calls_are_throttled_refused_ones_included(): void
    {
        $key = $this->key();

        for ($i = 0; $i < 60; $i++) {
            $this->feed($i % 2 ? $key : 'nefis_wrong')->assertStatus($i % 2 ? 200 : 401);
        }

        $this->feed($key)->assertStatus(429)->assertHeader('Retry-After');
        $this->getJson('/api/samira/order/1')->assertStatus(429);

        // A minute later the door opens again.
        $this->travel(61)->seconds();
        $this->feed($key)->assertOk();
    }

    public function test_nothing_is_cached_and_no_other_site_may_read_it(): void
    {
        $key = $this->key();

        $ok = $this->feed($key, [], ['Origin' => 'https://evil.example']);
        $ok->assertOk();
        $this->assertStringContainsString('no-store', (string) $ok->headers->get('Cache-Control'));
        $this->assertNull($ok->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($ok->headers->get('Set-Cookie'));

        $refused = $this->feed('nefis_wrong', [], ['Origin' => 'https://evil.example']);
        $refused->assertStatus(401);
        $this->assertStringContainsString('no-store', (string) $refused->headers->get('Cache-Control'));
        $this->assertNull($refused->headers->get('Access-Control-Allow-Origin'));

        // A browser's preflight is not answered with a permission either.
        $preflight = $this->call('OPTIONS', '/api/samira/feed', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ]);
        $this->assertNull($preflight->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($preflight->headers->get('Access-Control-Allow-Headers'));
    }

    public function test_it_cannot_write_anything(): void
    {
        $key = $this->key();
        $order = $this->order();

        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->withHeaders(['Authorization' => 'Bearer ' . $key])
                ->json($verb, '/api/samira/order/' . $order->id, ['status' => 'cancelled'])
                ->assertStatus(405);
            $this->withHeaders(['Authorization' => 'Bearer ' . $key])
                ->json($verb, '/api/samira/feed', ['status' => 'cancelled'])
                ->assertStatus(405);
        }

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_she_still_reads_while_the_shop_is_closed_for_maintenance(): void
    {
        $key = $this->key();
        Setting::put(Setting::MAINTENANCE, '1');

        $this->feed($key)->assertOk();
    }

    public function test_the_last_read_is_written_down_and_a_refused_one_is_not(): void
    {
        $key = $this->key();

        $this->feed('nefis_wrong')->assertStatus(401);
        $this->assertNull(SamiraKey::seenAt());

        $this->feed($key)->assertOk();
        $this->assertSame(now()->getTimestamp(), SamiraKey::seenAt()?->getTimestamp());
        $this->assertSame('127.0.0.1', SamiraKey::seenFrom());

        // Not a database write on every call: once a minute is enough.
        $this->travel(20)->seconds();
        $this->feed($key)->assertOk();
        $this->assertSame(now()->subSeconds(20)->getTimestamp(), SamiraKey::seenAt()?->getTimestamp());

        $this->travel(50)->seconds();
        $this->feed($key)->assertOk();
        $this->assertSame(now()->getTimestamp(), SamiraKey::seenAt()?->getTimestamp());
    }

    // ---- what she is told ----

    public function test_a_paid_order_is_news_once_with_its_boxes_and_a_first_name(): void
    {
        $key = $this->key();
        $order = $this->order(['payment_confirmed_at' => now()->subMinutes(10)]);

        $answer = $this->feed($key, ['since' => now()->subMinutes(30)->getTimestamp()])->assertOk();

        $answer->assertJsonCount(1, 'new_orders')
            ->assertJsonPath('new_orders.0.number', $order->id)
            ->assertJsonPath('new_orders.0.paid', true)
            ->assertJsonPath('new_orders.0.payment_method', 'card')
            ->assertJsonPath('new_orders.0.first_name', 'Aysel')
            ->assertJsonPath('new_orders.0.status', 'confirmed')
            ->assertJsonPath('new_orders.0.status_ru', 'подтверждён')
            ->assertJsonPath('new_orders.0.items.0.name', 'Sevgi qutusu')
            ->assertJsonPath('new_orders.0.items.0.quantity', 2)
            ->assertJsonPath('new_orders.0.items.0.chocolate', 'Milka')
            ->assertJsonPath('new_orders.0.delivery.date', '2026-10-23')
            ->assertJsonPath('new_orders.0.delivery.slot', '14:00–18:00')
            ->assertJsonPath('new_orders.0.delivery.name', 'Qapıya')
            ->assertJsonPath('new_orders.0.unix', now()->subMinutes(10)->getTimestamp());

        // 2 × (20 + 3) + 5 delivery
        $this->assertEquals(51, $answer->json('new_orders.0.total'));
        $this->assertEquals(46, $answer->json('new_orders.0.items.0.sum'));

        // The next question starts where this answer ended: told once.
        $this->travel(2)->minutes();
        $this->feed($key, ['since' => $answer->json('now')])->assertOk()->assertJsonCount(0, 'new_orders');
    }

    public function test_an_order_paid_before_since_or_not_paid_at_all_is_not_news(): void
    {
        $key = $this->key();
        $this->order(['payment_confirmed_at' => now()->subHours(3)]);
        $this->order(['status' => 'awaiting_payment', 'payment_confirmed_at' => null]);

        $this->feed($key, ['since' => now()->subHour()->getTimestamp()])
            ->assertOk()
            ->assertJsonCount(0, 'new_orders')
            ->assertJsonPath('today.awaiting_payment', 1);
    }

    public function test_an_order_the_site_asked_no_money_for_is_news_when_it_is_written(): void
    {
        $key = $this->key();
        $order = $this->order(['status' => 'pending', 'payment_confirmed_at' => null, 'payment_method' => 'transfer']);
        // The answer's upper edge is this second, exclusive.
        $this->travel(1)->seconds();

        $this->feed($key, ['since' => now()->subMinute()->getTimestamp()])
            ->assertOk()
            ->assertJsonCount(1, 'new_orders')
            ->assertJsonPath('new_orders.0.number', $order->id)
            ->assertJsonPath('new_orders.0.paid', false);
    }

    public function test_a_receipt_to_check_is_news(): void
    {
        $key = $this->key();
        $order = $this->order([
            'status' => 'payment_check', 'payment_confirmed_at' => null,
            'payment_method' => 'transfer', 'receipt_at' => now()->subMinutes(5),
        ]);

        $this->feed($key, ['since' => now()->subMinutes(15)->getTimestamp()])
            ->assertOk()
            ->assertJsonCount(0, 'new_orders')
            ->assertJsonCount(1, 'new_receipts')
            ->assertJsonPath('new_receipts.0.number', $order->id)
            ->assertJsonPath('new_receipts.0.first_name', 'Aysel')
            ->assertJsonPath('waiting.by_status.payment_check', 1);
    }

    public function test_no_customer_detail_ever_reaches_the_feed(): void
    {
        $key = $this->key();
        $order = $this->order(['payment_confirmed_at' => now()->subMinutes(5)]);
        $this->order([
            'status' => 'payment_check', 'payment_confirmed_at' => null, 'receipt_at' => now()->subMinutes(5),
        ]);

        $bodies = [
            $this->feed($key, ['since' => now()->subHour()->getTimestamp()])->assertOk()->getContent(),
            $this->withHeaders(['Authorization' => 'Bearer ' . $key])
                ->getJson('/api/samira/order/' . $order->id)->assertOk()->getContent(),
        ];

        foreach ($bodies as $body) {
            $text = json_encode(json_decode($body, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ([self::PHONE, '55 123', '55123456', self::ADDRESS, 'Nizami', self::EMAIL, 'example.com',
                self::SURNAME, 'Leyla', 'Gənclik', 'Qapının kodu', 'secret-face', 'Səni sevirəm'] as $private) {
                $this->assertStringNotContainsString($private, $text, 'The feed gave away: ' . $private);
            }
            $this->assertStringContainsString('Aysel', $text);
        }
    }

    public function test_today_and_what_is_waiting_add_up(): void
    {
        $key = $this->key();
        $this->order(['payment_confirmed_at' => now()->subHours(2)]);                       // 51
        $this->order(['payment_confirmed_at' => now()->subHour(), 'status' => 'ready',
            'delivery_date' => today()->toDateString()]);                                   // 51, goes out today
        $this->order(['payment_confirmed_at' => now()->subMinutes(5), 'status' => 'cancelled']); // not a sale
        $this->order(['payment_confirmed_at' => now()->subDay(), 'status' => 'pending',
            'delivery_date' => today()->subDay()->toDateString()]);                          // yesterday's, late
        $this->order(['payment_confirmed_at' => now()->subDays(2), 'status' => 'completed']);

        $answer = $this->feed($key)->assertOk()
            ->assertJsonPath('today.date', '2026-10-21')
            ->assertJsonPath('today.orders', 2)
            ->assertJsonPath('today.deliveries', 1)
            ->assertJsonPath('waiting.count', 3)
            ->assertJsonPath('waiting.by_status.confirmed', 1)
            ->assertJsonPath('waiting.by_status.ready', 1)
            ->assertJsonPath('waiting.by_status.pending', 1)
            ->assertJsonPath('waiting.late', 1)
            ->assertJsonCount(3, 'waiting.orders')
            // Late first: ordered by delivery day.
            ->assertJsonPath('waiting.orders.0.late', true)
            ->assertJsonPath('waiting.orders.0.status_ru', 'в ожидании');

        $this->assertEquals(102, $answer->json('today.sum'));
    }

    public function test_since_is_kept_within_a_week_and_defaults_to_the_start_of_today(): void
    {
        $key = $this->key();

        $this->feed($key)->assertJsonPath('since', today()->getTimestamp());
        $this->feed($key, ['since' => 0])->assertJsonPath('since', now()->subDays(7)->getTimestamp());
        $this->feed($key, ['since' => now()->addDay()->getTimestamp()])->assertJsonPath('since', now()->getTimestamp());
        $this->feed($key, ['since' => 'yesterday'])->assertJsonPath('since', today()->getTimestamp());
        $this->feed($key, ['since' => ['1', '2']])->assertOk();
    }

    public function test_one_order_by_its_number(): void
    {
        $key = $this->key();
        $courier = User::factory()->create(['name' => 'Elvin Quliyev', 'role' => User::COURIER, 'is_admin' => false]);
        $order = $this->order(['status' => 'ready', 'courier_id' => $courier->id, 'promo_code' => 'YAZ10']);

        $this->withHeaders(['Authorization' => 'Bearer ' . $key])
            ->getJson('/api/samira/order/' . $order->id)
            ->assertOk()
            ->assertJsonPath('order.number', $order->id)
            ->assertJsonPath('order.status', 'ready')
            ->assertJsonPath('order.status_ru', 'готов')
            ->assertJsonPath('order.first_name', 'Aysel')
            ->assertJsonPath('order.courier_first_name', 'Elvin')
            ->assertJsonPath('order.promo_code', 'YAZ10')
            ->assertJsonPath('order.paid_at', $order->payment_confirmed_at->toIso8601String())
            ->assertJsonPath('order.items.0.name', 'Sevgi qutusu');

        $this->withHeaders(['Authorization' => 'Bearer ' . $key])
            ->getJson('/api/samira/order/999999')
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');

        $this->withHeaders(['Authorization' => 'Bearer ' . $key])
            ->getJson('/api/samira/order/abc')
            ->assertStatus(404);
    }

    // ---- the owner's page ----

    public function test_only_the_owner_reaches_the_samira_page(): void
    {
        $this->assertFalse(Samira::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::MANAGER, 'is_admin' => false]));
        $this->assertFalse(Samira::canAccess());
        $this->get('/admin/samira')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]));
        $this->assertTrue(Samira::canAccess());
        $this->get('/admin/samira')->assertOk()->assertSee('Söndürülüb')->assertSee('Açar yarat');
    }

    public function test_the_owner_makes_a_key_sees_it_once_renews_it_and_switches_it_off(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]));

        $page = Livewire::test(Samira::class)->callAction('make');
        $first = $page->get('freshKey');

        $this->assertIsString($first);
        $page->assertSee($first)->assertSee('Вставьте его в окно');
        $this->assertTrue(SamiraKey::matches($first));

        // Opened again, the page has no key to show: only its hash is kept.
        Livewire::test(Samira::class)->assertSet('freshKey', null)->assertDontSee($first)->assertSee('Qoşulub');

        $this->feed($first)->assertOk();
        $this->flushHeaders();
        $this->get('/admin/samira')->assertOk()->assertSee('Samira son dəfə oxuyub');

        $renewed = Livewire::test(Samira::class)->callAction('renew');
        $second = $renewed->get('freshKey');
        $this->assertNotSame($first, $second);
        $this->assertFalse(SamiraKey::matches($first));
        $this->assertTrue(SamiraKey::matches($second));

        Livewire::test(Samira::class)->callAction('off');
        $this->assertFalse(SamiraKey::exists());
        $this->assertFalse(SamiraKey::matches($second));
    }
}
