<?php

namespace Tests\Feature;

use App\Filament\Pages\DataAndFiles;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Support\CookieNotice;
use App\Support\DataMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The screen that answers «what does this shop keep about people».
 *
 * The page's whole value is that it does not lie, so that is what is tested:
 * the locked-column list comes from the models rather than from a list that
 * could drift, a row written before the lock is reported as still open
 * instead of being quietly counted as safe, and a wrong APP_KEY is said out
 * loud rather than shrugged off.
 */
class DataAndFilesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();          // the file tallies are cached for ten minutes
    }

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]);
    }

    /** Puts a file's clock back, so the thirty-day window really applies. */
    private function age(string $path, int $days = 60): void
    {
        touch(Storage::disk('public')->path($path), now()->subDays($days)->getTimestamp());
    }

    private function order(array $extra = []): Order
    {
        return Order::create($extra + [
            'user_id' => User::factory()->create()->id,
            'status' => 'pending',
            'locale' => 'az',
        ]);
    }

    public function test_a_customer_cannot_reach_the_page(): void
    {
        // Nobody signed in, then somebody signed in who is not staff.
        $this->assertFalse(DataAndFiles::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::CUSTOMER, 'is_admin' => false]));
        $this->assertFalse(DataAndFiles::canAccess());
    }

    public function test_the_owner_sees_the_page(): void
    {
        $this->order([
            'contact_phone' => '+994 55 123 45 67',
            'delivery_address' => 'Nizami küç. 12',
        ]);

        $this->actingAs($this->owner());
        $this->assertTrue(DataAndFiles::canAccess());

        $this->get(DataAndFiles::getUrl())
            ->assertOk()
            ->assertSee('Ziyarətçinin brauzerində nə saxlanılır')
            ->assertSee('Bazada şifrələnən məlumat')
            ->assertSee('Diskdəki fayllar')
            ->assertSee('delivery_address');
    }

    public function test_the_locked_list_is_read_off_the_models(): void
    {
        $columns = array_map(
            fn (array $row) => $row['table'] . '.' . $row['column'],
            DataMap::locked()
        );

        // Every cast the models declare has to turn up here.
        foreach (['orders.contact_phone', 'orders.delivery_address', 'orders.note',
            'chat_messages.phone', 'corporate_requests.person',
            'reviews.shown_name', 'contact_messages.note'] as $expected) {
            $this->assertContains($expected, $columns, "$expected is missing from the page");
        }

        // And nothing the shop searches by may appear.
        foreach ($columns as $column) {
            $this->assertStringNotContainsString('users.', $column);
        }
    }

    public function test_a_row_written_before_the_lock_is_counted_as_still_open(): void
    {
        $order = $this->order(['contact_phone' => '+994 55 123 45 67']);

        // Straight into the table, model and cast bypassed — exactly what a
        // row written before this feature existed looks like.
        DB::table('orders')->where('id', $order->id)
            ->update(['delivery_address' => 'Nizami küç. 12, mən. 5']);

        $rows = collect(DataMap::locked())->keyBy(fn ($r) => $r['table'] . '.' . $r['column']);

        $this->assertSame(1, $rows['orders.delivery_address']['filled']);
        $this->assertSame(0, $rows['orders.delivery_address']['locked']);
        $this->assertSame(1, $rows['orders.delivery_address']['plain']);

        // The one the model wrote is locked.
        $this->assertSame(1, $rows['orders.contact_phone']['locked']);
        $this->assertSame(0, $rows['orders.contact_phone']['plain']);

        $this->actingAs($this->owner())
            ->get(DataAndFiles::getUrl())
            ->assertSee('sətir hələ açıq qalıb');
    }

    public function test_the_key_check_says_yes_no_or_nothing_yet(): void
    {
        // Nothing stored: there is nothing to prove, and it says so.
        $this->assertNull(DataMap::keyOpens());

        $this->order(['delivery_address' => 'Nizami küç. 12']);
        $this->assertTrue(DataMap::keyOpens());

        // A database carried over from a site with a different key: the
        // envelope is there, the mac does not check out.
        DB::table('orders')->update([
            'delivery_address' => base64_encode(json_encode([
                'iv' => base64_encode('0123456789abcdef'),
                'value' => base64_encode('nonsense'),
                'mac' => str_repeat('0', 64),
                'tag' => '',
            ])),
        ]);

        $this->assertFalse(DataMap::keyOpens());

        $this->actingAs($this->owner())
            ->get(DataAndFiles::getUrl())
            ->assertSee('açar uyğun gəlmir');
    }

    public function test_every_cookie_the_page_names_is_one_the_site_really_sets(): void
    {
        $named = array_column(DataMap::inTheBrowser(), 'name');

        $this->assertContains(config('session.cookie'), $named);
        $this->assertContains(CookieNotice::KEY, $named);
        $this->assertContains('nefis-theme', $named);

        // Exactly one entry may be optional: Google's. If a second thing
        // ever needs consent, the strip's wording has to change too.
        $optional = array_filter(DataMap::inTheBrowser(), fn (array $r) => ! $r['needed']);
        $this->assertCount(1, $optional);
    }

    public function test_the_page_says_why_the_strip_is_not_shown(): void
    {
        Setting::put(Setting::COOKIE_NOTICE, false);

        $state = DataMap::noticeState();
        $this->assertFalse($state['shown']);
        $this->assertNotSame('', $state['why']);

        $this->actingAs($this->owner())
            ->get(DataAndFiles::getUrl())
            ->assertSee('Kuki lenti göstərilmir');
    }

    public function test_the_disk_is_counted_and_a_stray_photo_is_found(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('cart-photos/kept.webp', 'x');
        Storage::disk('public')->put('cart-photos/stray.webp', 'yy');

        // One of them belongs to an order; the other to nobody.
        $order = $this->order();
        $order->items()->create([
            'product_name' => 'Qutu', 'quantity' => 1, 'price' => 10,
            'customer_photos' => ['cart-photos/kept.webp'], 'custom_texts' => [],
        ]);

        $folders = collect(DataMap::files(true))->keyBy('folder');
        $this->assertSame(2, $folders['cart-photos']['files']);
        $this->assertSame(3, $folders['cart-photos']['bytes']);

        /* Both files were made a moment ago, so the thirty-day rule keeps
           them: that is the point of the rule, and the count has to honour
           it. */
        $this->assertSame(0, DataMap::strays(30, true)['files']);

        // Put both clocks back. Only the one no order mentions is stray.
        $this->age('cart-photos/kept.webp');
        $this->age('cart-photos/stray.webp');

        $strays = DataMap::strays(30, true);
        $this->assertSame(1, $strays['files']);
        $this->assertSame(2, $strays['bytes']);
    }

    public function test_the_sweep_removes_the_stray_and_keeps_the_one_an_order_points_at(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('cart-photos/kept.webp', 'x');
        Storage::disk('public')->put('cart-photos/stray.webp', 'yy');
        Storage::disk('public')->put('cart-videos/also-stray.mp4', 'zzz');

        $order = $this->order();
        $order->items()->create([
            'product_name' => 'Qutu', 'quantity' => 1, 'price' => 10,
            'customer_photos' => ['cart-photos/kept.webp'], 'custom_texts' => [],
        ]);

        foreach (['cart-photos/kept.webp', 'cart-photos/stray.webp',
            'cart-videos/also-stray.mp4'] as $path) {
            $this->age($path);
        }

        $gone = DataMap::sweep();

        $this->assertSame(2, $gone['files']);
        $this->assertSame(5, $gone['bytes']);

        Storage::disk('public')->assertExists('cart-photos/kept.webp');
        Storage::disk('public')->assertMissing('cart-photos/stray.webp');
        Storage::disk('public')->assertMissing('cart-videos/also-stray.mp4');
    }

    public function test_the_sweep_leaves_a_photograph_somebody_is_still_working_on(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cart-photos/just-now.webp', 'x');

        /* Nothing points at it — the box is half made in a basket, and a
           session is not a table. The thirty-day window is the only thing
           standing between that customer and a lost photograph, so the
           default must never be zero. */
        $gone = DataMap::sweep();

        $this->assertSame(0, $gone['files']);
        Storage::disk('public')->assertExists('cart-photos/just-now.webp');
    }

    public function test_it_counts_what_is_happening_without_falling_over_on_an_empty_shop(): void
    {
        $rows = DataMap::happening();

        $this->assertNotSame([], $rows);
        foreach ($rows as $row) {
            $this->assertIsNumeric($row['value']);
            $this->assertNotSame('', $row['label']);
        }

        $this->order();
        $after = collect(DataMap::happening())->firstWhere('label', 'Sifariş (24 saat)');
        $this->assertSame('1', $after['value']);
    }
}
