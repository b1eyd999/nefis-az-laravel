<?php

namespace Tests\Feature;

use App\Filament\Pages\ErrorLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The owner can read what went wrong, without a terminal.
 *
 * There is no shell on that host. A «500 Server Error» in front of him used
 * to mean a screenshot and a guess, because the one record of what actually
 * threw sat in a file nothing in the shop could open.
 *
 * Three things are worth guarding. That a stack trace stays attached to the
 * line it belongs to, rather than its «#12 /path/file.php(88)» lines being
 * read as faults of their own. That the ordinary chatter of a working shop —
 * every payment, every message sent — is not shown as a fault. And that the
 * page is the owner's alone: a log carries addresses, telephone numbers and
 * whatever a customer typed into a form.
 */
class TheErrorLogIsReadableTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();

        /* The shop's own storage folder is not written into by a test: the
           log the developer is reading would be rewritten under him. */
        $this->storage = sys_get_temp_dir() . '/nefis-log-test-' . uniqid();
        @mkdir($this->storage . '/logs', 0777, true);
        $this->app->useStoragePath($this->storage);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storage . '/logs/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->storage . '/logs');
        @rmdir($this->storage);

        parent::tearDown();
    }

    private function wrote(string $text): void
    {
        file_put_contents($this->storage . '/logs/laravel.log', $text);
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->actingAs($owner);

        return $owner;
    }

    private function page(): ErrorLog
    {
        return new ErrorLog();
    }

    // ------------------------------------------------------------ who sees it

    public function test_the_owner_opens_it(): void
    {
        $this->owner();
        $this->wrote("[2026-11-05 09:00:00] production.ERROR: Qutu yoxdur\n");

        $this->get('/admin/error-log')->assertOk()->assertSee('Qutu yoxdur');
    }

    public function test_a_manager_does_not(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'role' => User::MANAGER]));

        $this->get('/admin/error-log')->assertForbidden();
    }

    public function test_a_stranger_does_not(): void
    {
        $this->get('/admin/error-log')->assertRedirect();
    }

    // ---------------------------------------------------------- what it shows

    /**
     * A trace belongs to the fault above it, not to itself.
     *
     * Split the file on every line that looks like a line and the forty
     * «#0 /path/file.php(88): Foo->bar()» rows under one exception each
     * become an entry, and the fault itself is pushed off the screen.
     */
    public function test_a_stack_trace_stays_with_the_fault_it_belongs_to(): void
    {
        $this->owner();
        $this->wrote(<<<'LOG'
            [2026-11-05 09:00:00] production.ERROR: Call to a member function name() on null in /home/app/Models/Box.php:42
            [stacktrace]
            #0 /home/app/Http/Controllers/BoxController.php(88): App\Models\Box->label()
            #1 /home/vendor/laravel/framework/src/Illuminate/Routing/Route.php(260): App\Http\Controllers\BoxController->show()
            #2 {main}

            LOG);

        $entries = $this->page()->entries();

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Call to a member function name() on null', $entries[0]['headline']);
        $this->assertStringContainsString('BoxController.php(88)', $entries[0]['text']);
    }

    /** The line says where it threw, without the host's folders in front. */
    public function test_the_place_it_threw_is_picked_out(): void
    {
        $this->owner();
        $this->wrote('[2026-11-05 09:00:00] production.ERROR: Boom in ' . base_path() . '/app/Models/Box.php:42' . "\n");

        $this->assertSame('app/Models/Box.php:42', $this->page()->entries()[0]['where']);
    }

    /**
     * The ordinary noise of a working shop is not a fault.
     *
     * The file carries a line from every payment taken and every message
     * sent. Shown here they would bury the one line that matters.
     */
    public function test_the_everyday_chatter_is_not_shown(): void
    {
        $this->owner();
        $this->wrote(<<<'LOG'
            [2026-11-05 08:00:00] production.INFO: ePoint ödənişi təsdiqləndi #412
            [2026-11-05 08:30:00] production.DEBUG: Telegram sendMessage ok
            [2026-11-05 09:00:00] production.ERROR: Bu qırılıb
            [2026-11-05 09:05:00] production.WARNING: Yavaş sorğu

            LOG);

        $entries = $this->page()->entries();

        $this->assertCount(1, $entries);
        $this->assertSame('Bu qırılıb', $entries[0]['headline']);
    }

    public function test_the_loud_levels_all_count(): void
    {
        $this->owner();
        $this->wrote(<<<'LOG'
            [2026-11-05 09:00:00] production.ERROR: bir
            [2026-11-05 09:01:00] production.CRITICAL: iki
            [2026-11-05 09:02:00] production.ALERT: üç
            [2026-11-05 09:03:00] production.EMERGENCY: dörd

            LOG);

        $this->assertSame(['dörd', 'üç', 'iki', 'bir'],
            array_column($this->page()->entries(), 'headline'));
    }

    public function test_the_newest_fault_stands_first(): void
    {
        $this->owner();
        $this->wrote(<<<'LOG'
            [2026-11-05 09:00:00] production.ERROR: köhnə
            [2026-11-05 11:00:00] production.ERROR: təzə

            LOG);

        $this->assertSame('təzə', $this->page()->entries()[0]['headline']);
    }

    /**
     * The stamp is read in the shop's own time, not the server's.
     *
     * Monolog writes it in the app's timezone — Asia/Baku — so parsing it as
     * UTC would show every fault four hours before it happened, and the
     * owner comparing it against «when I clicked» would find nothing.
     */
    public function test_the_time_is_the_shops_own(): void
    {
        $this->owner();
        $this->wrote("[2026-11-05 09:00:00] production.ERROR: vaxt\n");

        $at = $this->page()->entries()[0]['at'];

        $this->assertSame('05.11.2026 09:00:00', $at->format('d.m.Y H:i:s'));
        $this->assertSame(config('app.timezone'), $at->timezone->getName());
    }

    // --------------------------------------------------------- when it is bare

    public function test_an_empty_log_is_not_an_error_of_its_own(): void
    {
        $this->owner();
        $this->wrote('');

        $this->assertSame([], $this->page()->entries());
        $this->get('/admin/error-log')->assertOk()->assertSee('Xəta yoxdur');
    }

    public function test_no_log_file_at_all_is_not_an_error_either(): void
    {
        $this->owner();

        $this->assertNull($this->page()->file());
        $this->assertSame([], $this->page()->entries());
        $this->get('/admin/error-log')->assertOk();
    }

    /**
     * Only the end of the file is read.
     *
     * The live log runs to megabytes. Reading the whole of it to show forty
     * lines is how an admin page becomes the thing that takes the host down.
     */
    public function test_a_huge_log_is_read_from_the_end_only(): void
    {
        $this->owner();

        /* Thirty faults, each dragging a trace of some forty thousand
           characters behind it — a megabyte in all, which is the size the
           live file reaches. Only the last few fit in the tail that is read. */
        $trace = str_repeat("#0 /home/app/Thing.php(12): Foo->bar()\n", 1000);
        $log = '';

        for ($i = 1; $i <= 30; $i++) {
            $log .= '[2026-11-05 09:00:00] production.ERROR: sətir ' . $i . "\n" . $trace;
        }

        $this->wrote($log);

        $this->assertGreaterThan(1_000_000, $this->page()->fileSize());

        $headlines = array_column($this->page()->entries(), 'headline');

        $this->assertSame('sətir 30', $headlines[0]);
        $this->assertNotEmpty($headlines);
        // Read whole, all thirty would be here — the first of them included.
        $this->assertNotContains('sətir 1', $headlines);
        $this->assertLessThan(30, count($headlines));
    }

    /** Half an entry at the top of the tail is dropped, not shown as a fault. */
    public function test_the_half_entry_the_tail_begins_on_is_dropped(): void
    {
        $this->owner();
        $this->wrote("#7 /home/app/Thing.php(12): Foo->bar()\n"
            . "[2026-11-05 09:00:00] production.ERROR: tam sətir\n");

        $entries = $this->page()->entries();

        $this->assertCount(1, $entries);
        $this->assertSame('tam sətir', $entries[0]['headline']);
    }

    // ------------------------------------------------------------ the button

    public function test_the_owner_empties_the_log(): void
    {
        $this->owner();
        $this->wrote("[2026-11-05 09:00:00] production.ERROR: silinəcək\n");

        \Livewire\Livewire::test(ErrorLog::class)
            ->callAction('clear')
            ->assertHasNoActionErrors();

        $this->assertSame('', file_get_contents($this->storage . '/logs/laravel.log'));
        $this->assertSame([], $this->page()->entries());
    }
}
