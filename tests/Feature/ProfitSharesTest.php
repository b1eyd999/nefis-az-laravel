<?php

namespace Tests\Feature;

use App\Filament\Pages\Balance;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The profit split, changed on the page where it is read.
 */
class ProfitSharesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->admin = User::factory()->create(['name' => 'Admin JM', 'role' => User::ADMIN, 'profit_percent' => 22]);
        $this->manager = User::factory()->create(['name' => 'İsa', 'role' => User::MANAGER, 'profit_percent' => 22]);
    }

    public function test_the_owner_changes_the_split_from_the_balance_page(): void
    {
        Livewire::actingAs($this->admin)->test(Balance::class)
            ->callAction('shares', data: ['u' . $this->admin->id => 40, 'u' . $this->manager->id => 25])
            ->assertHasNoActionErrors();

        $this->assertEquals(40, $this->admin->fresh()->profit_percent);
        $this->assertEquals(25, $this->manager->fresh()->profit_percent);

        // And the page shows the new split, with the rest left to the business.
        $report = Livewire::actingAs($this->admin)->test(Balance::class)->instance()->getReportProperty();
        $this->assertSame([40.0, 25.0, 35.0], array_map(fn ($s) => (float) $s['percent'], $report['shares']));
        $this->assertTrue($report['shares'][2]['rest']);
    }

    public function test_more_than_the_whole_profit_cannot_be_handed_out(): void
    {
        Livewire::actingAs($this->admin)->test(Balance::class)
            ->callAction('shares', data: ['u' . $this->admin->id => 70, 'u' . $this->manager->id => 45]);

        $this->assertEquals(22, $this->admin->fresh()->profit_percent, 'nothing should have been written');
        $this->assertEquals(22, $this->manager->fresh()->profit_percent);
    }

    public function test_a_manager_is_not_offered_the_button(): void
    {
        Livewire::actingAs($this->manager)->test(Balance::class)
            ->assertActionDoesNotExist('shares');
    }
}
