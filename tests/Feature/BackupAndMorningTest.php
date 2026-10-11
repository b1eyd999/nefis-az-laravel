<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Support\Backup;
use App\Support\Telegram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Two things the shop did not have: a copy of its own database, and anything
 * that tells the owner the day without being asked.
 */
class BackupAndMorningTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // The copies this test wrote are not left behind.
        foreach (Backup::all() as $row) {
            @unlink($row['path']);
        }

        parent::tearDown();
    }

    private function box(): Product
    {
        $box = Product::firstWhere('slug', 'test');
        if ($box) {
            return $box;
        }

        $box = Product::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true, 'price' => 20,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100,
            'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function order(array $over = []): Order
    {
        $order = Order::create(array_replace([
            'user_id' => User::factory()->create()->id,
            'status' => 'confirmed',
            'delivery_method_id' => DeliveryMethod::where('type', 'door')->value('id'),
            'delivery_type' => 'door',
            'delivery_name' => 'Qapıya',
            'delivery_price' => 0,
            'contact_phone' => '1',
            'delivery_address' => 'Bakı',
        ], $over));
        $order->items()->create([
            'product_id' => $this->box()->id, 'product_name' => 'Test',
            'customer_photos' => [], 'custom_texts' => [], 'quantity' => 1, 'unit_price' => 20,
        ]);

        return $order->fresh('items');
    }

    // ---- the copy ----

    /**
     * The database written out as SQL.
     *
     * The test database is held in memory, so there is no file to copy and
     * the writer takes over — which is the path the live MySQL takes, and so
     * the one worth testing.
     */
    public function test_a_copy_is_written_and_holds_the_rows(): void
    {
        $this->order();
        Setting::put(Setting::BACKUP, '1');

        $this->artisan('db:backup')->assertSuccessful();

        $copies = Backup::all();
        $this->assertCount(1, $copies);
        $this->assertGreaterThan(0, $copies[0]['size']);

        $sql = self::read($copies[0]['path']);
        $this->assertStringContainsString('INSERT INTO `orders`', $sql);
        $this->assertStringContainsString('Bakı', $sql, 'the data itself, not only the shape');
        $this->assertStringContainsString('CREATE TABLE', $sql, 'and how to build the table back');
    }

    /** A dump of who was signed in yesterday helps nobody restore a shop. */
    public function test_the_sessions_are_left_out(): void
    {
        Setting::put(Setting::BACKUP, '1');
        $this->artisan('db:backup')->assertSuccessful();

        $sql = self::read(Backup::all()[0]['path']);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `cache`', $sql);
    }

    public function test_old_copies_are_swept(): void
    {
        Setting::put(Setting::BACKUP, '1');
        Setting::put(Setting::BACKUP_KEEP, '2');

        foreach (['2026-01-01_000000', '2026-01-02_000000', '2026-01-03_000000'] as $stamp) {
            Backup::make($stamp);
        }
        $this->assertCount(3, Backup::all());

        Backup::sweep();

        $left = array_column(Backup::all(), 'name');
        $this->assertCount(2, $left);
        $this->assertStringContainsString('2026-01-03', $left[0], 'the newest is kept');
        $this->assertStringContainsString('2026-01-02', $left[1]);
    }

    public function test_it_can_be_switched_off(): void
    {
        Setting::put(Setting::BACKUP, '0');

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertSame([], Backup::all());
    }

    /**
     * The shape of every table is kept, even where the rows are not.
     *
     * `migrations` IS restored and it says the sessions and cache tables were
     * created. Leave their CREATE out and the dump imports perfectly, then
     * every page dies on "Base table 'sessions' doesn't exist" — and
     * `artisan migrate` answers "Nothing to migrate", because the restored
     * migrations table swears it already ran. With no SSH, that is a dead
     * shop.
     */
    public function test_the_skipped_tables_keep_their_shape(): void
    {
        Setting::put(Setting::BACKUP, '1');
        $this->artisan('db:backup')->assertSuccessful();

        $sql = self::read(Backup::all()[0]['path']);

        foreach (['sessions', 'cache', 'cache_locks', 'jobs'] as $table) {
            $this->assertMatchesRegularExpression('/CREATE TABLE[^;]*["`]' . $table . '["`]/i', $sql,
                $table . ' must be buildable again');
            $this->assertStringNotContainsString('INSERT INTO `' . $table . '`', $sql,
                $table . ' rows are not worth keeping');
        }

        // And the table that claims they exist is itself kept, rows and all.
        $this->assertStringContainsString('INSERT INTO `migrations`', $sql);
    }

    /** A finished dump says so on its last line. */
    public function test_a_finished_dump_says_so_and_a_half_one_is_not_counted(): void
    {
        Setting::put(Setting::BACKUP, '1');
        $this->artisan('db:backup')->assertSuccessful();

        $newest = Backup::all()[0];
        $this->assertStringContainsString(Backup::MARKER, self::read($newest['path']));
        $this->assertTrue(Backup::whole($newest['path']));

        // Something that was being written when the lights went out is not a
        // backup: it is not listed, cannot be downloaded, and cannot push a
        // good copy out of the kept slots.
        $half = Backup::directory() . '/nefis_2026-01-01_000000.sql';
        file_put_contents($half, "-- Nefis.az
INSERT INTO `orders` (`id`) VALUES (1)
");
        $this->assertFalse(Backup::whole($half));

        $part = Backup::directory() . '/nefis_2026-01-02_000000.sql.part';
        file_put_contents($part, 'half a dump');
        $this->assertNotContains('nefis_2026-01-02_000000.sql.part', array_column(Backup::all(), 'name'));
        $this->assertNull(Backup::find('nefis_2026-01-02_000000.sql.part'));

        @unlink($half);
        @unlink($part);
    }

    /**
     * When the copy cannot be written, the owner is told.
     *
     * A backup that quietly stopped working is worse than none, because he
     * believes he has one.
     */
    public function test_a_failed_copy_is_shouted_about(): void
    {
        Setting::put(Setting::BACKUP, '1');
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '42');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // Nowhere to write: the folder's own name is taken by a file.
        $folder = Backup::directory();
        foreach (glob($folder . '/*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        $this->assertTrue(@rmdir($folder), 'the folder has to be gone for this test to mean anything');
        file_put_contents($folder, 'not a folder');

        $this->artisan('db:backup')->assertFailed();

        Http::assertSent(fn ($request) => str_contains((string) ($request->data()['text'] ?? ''), 'Baza kopyası alınmadı'));

        @unlink($folder);
    }

    /**
     * A database that will not answer is not an answer of "no".
     *
     * Asking whether backups are switched on is itself a database read, and
     * it used to sit outside the guard — so on the one night the alert
     * existed for, the job threw before it and died into cron's /dev/null.
     */
    public function test_an_unreadable_setting_does_not_stop_the_night(): void
    {
        Setting::put(Setting::BACKUP, '1');

        // The settings table is gone and nothing is remembered about it.
        \Illuminate\Support\Facades\Cache::flush();
        \Illuminate\Support\Facades\Schema::drop('settings');
        $this->assertThrows(fn () => Backup::enabled(), \Throwable::class);

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertCount(1, Backup::all(), 'it went ahead rather than reading silence as "no"');
    }

    /** And the morning note notices when the nightly job stopped running. */
    public function test_the_morning_note_says_when_the_backups_went_stale(): void
    {
        Setting::put(Setting::MORNING_NOTE, '1');
        Setting::put(Setting::BACKUP, '1');
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '42');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        // Nothing at all.
        $this->artisan('shop:morning')->assertSuccessful();
        Http::assertSent(fn ($r) => str_contains((string) ($r->data()['text'] ?? ''), 'heç bir kopyası yoxdur'));

        // One, but from last week.
        $old = Backup::make('2026-01-01_000000');
        touch($old, now()->subDays(7)->getTimestamp());
        $this->artisan('shop:morning')->assertSuccessful();
        Http::assertSent(fn ($r) => str_contains((string) ($r->data()['text'] ?? ''), 'gecə kopyası alınmır'));

        // And a fresh one says nothing at all.
        Backup::make();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $this->artisan('shop:morning')->assertSuccessful();
        Http::assertSent(fn ($r) => ! str_contains((string) ($r->data()['text'] ?? ''), 'kopya'));
    }

    /** A name off a page is read as a name and nothing else. */
    public function test_a_name_cannot_reach_out_of_the_folder(): void
    {
        $this->assertNull(Backup::find('../../../.env'));
        $this->assertNull(Backup::find('.env'));
        $this->assertNull(Backup::find('nefis_nothing.sql'));

        $path = Backup::make('2026-01-01_000000');
        $this->assertSame($path, Backup::find(basename($path)));
    }

    /** The owner's page, and only the owner's. */
    public function test_only_the_owner_reaches_the_copies(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/backups')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/backups')->assertOk();
    }

    public function test_it_is_on_the_schedule(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')->implode(' ');

        $this->assertStringContainsString('db:backup', $commands);
        $this->assertStringContainsString('shop:morning', $commands);
    }

    // ---- the morning note ----

    public function test_the_morning_note_says_what_the_day_holds(): void
    {
        Setting::put(Setting::MORNING_NOTE, '1');
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '42');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $today = $this->order(['delivery_date' => today(), 'delivery_slot' => '10:00–14:00']);
        $late = $this->order(['delivery_date' => today()->subDays(2)]);
        $this->order(['delivery_date' => today()->addDay()]);
        $this->order(['status' => 'awaiting_payment', 'delivery_date' => today()->addDays(3)]);
        ContactMessage::create(['name' => 'Aygün', 'phone' => '1', 'message' => 'Salam']);

        $this->artisan('shop:morning')->assertSuccessful();

        Http::assertSent(function ($request) use ($today, $late) {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Bu gün 1')
                && str_contains($text, '#' . $today->id)
                && str_contains($text, '10:00–14:00')
                && str_contains($text, 'Gecikən 1')
                && str_contains($text, '#' . $late->id)
                && str_contains($text, 'Sabah: 1')
                && str_contains($text, 'Ödəniş gözləyir: 1')
                && str_contains($text, 'Cavabsız mesaj: 1');
        });
    }

    /** A finished or cancelled order's day is not late. */
    public function test_a_finished_order_is_not_counted_late(): void
    {
        Setting::put(Setting::MORNING_NOTE, '1');
        Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '42');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $done = $this->order(['delivery_date' => today()->subDays(3)]);
        $done->forceFill(['status' => 'completed'])->save();
        $dropped = $this->order(['delivery_date' => today()->subDays(3)]);
        $dropped->forceFill(['status' => 'cancelled'])->save();

        $this->artisan('shop:morning')->assertSuccessful();

        Http::assertSent(fn ($request) => ! str_contains((string) ($request->data()['text'] ?? ''), 'Gecikən'));
    }

    public function test_the_morning_note_can_be_switched_off(): void
    {
        Setting::put(Setting::MORNING_NOTE, '0');
        Http::fake();

        $this->artisan('shop:morning')->assertSuccessful();

        Http::assertNothingSent();
    }

    /** The orders list opens on the day's work. */
    public function test_the_orders_list_has_the_day_tabs(): void
    {
        $this->order(['delivery_date' => today()]);
        $late = $this->order(['delivery_date' => today()->subDay()]);
        $admin = User::factory()->create(['role' => User::ADMIN]);

        $page = \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Resources\OrderResource\Pages\ListOrders::class);

        $page->assertSee('Bu gün')->assertSee('Gecikən')->assertSee('Ödəniş gözləyir');

        // «Gecikən» shows the one whose day has passed, and nothing else.
        $page->set('activeTab', 'late')
            ->assertCanSeeTableRecords([$late])
            ->assertCountTableRecords(1);
    }

    private static function read(string $path): string
    {
        return str_ends_with($path, '.gz')
            ? (string) gzdecode((string) file_get_contents($path))
            : (string) file_get_contents($path);
    }
}
