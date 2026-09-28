<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\Setting;
use App\Models\User;
use App\Support\Scheduler;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shop's timed work waits on one cron entry on a hosting with no SSH.
 * Nothing about that fails loudly, so the admin has to say whether it runs.
 */
class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_the_scheduler_leaves_a_mark_every_time_it_runs(): void
    {
        $this->assertNull(Scheduler::lastRun());
        $this->assertFalse(Scheduler::isRunning());

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNotNull(Scheduler::lastRun());
        $this->assertTrue(Scheduler::isRunning());
    }

    public function test_a_stopped_cron_is_not_read_as_a_running_one(): void
    {
        Setting::put(Setting::SCHEDULE_SEEN, now()->subMinutes(Scheduler::QUIET_MINUTES + 1)->toDateTimeString());
        $this->assertFalse(Scheduler::isRunning());

        // Nonsense in the column is "we do not know", not a crash.
        Setting::put(Setting::SCHEDULE_SEEN, 'nə vaxtsa');
        $this->assertNull(Scheduler::lastRun());
        $this->assertFalse(Scheduler::isRunning());
    }

    public function test_the_admin_says_so_and_prints_the_line_to_paste(): void
    {
        $this->admin();

        Livewire::test(SiteSettings::class)
            ->assertSee('Avtomatik işlər')
            ->assertSee('İşləmir')
            ->assertSee('schedule:run')
            ->assertSee(base_path('artisan'));

        $this->artisan('schedule:run');

        Livewire::test(SiteSettings::class)
            ->assertSee('İşləyir')
            // With the cron running there is nothing to paste.
            ->assertDontSee('schedule:run');
    }

    public function test_the_abandoned_order_job_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('orders:expire-unpaid');
    }
}
