<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Menu;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Məhsullar" menu is the owner's to arrange.
 *
 * Every line used to appear because its own feature was switched on
 * somewhere else, and there was no way to hold a page back while it was
 * being written or to say "new" beside one.
 */
class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_menu_shows_every_ready_line_until_the_owner_says_otherwise(): void
    {
        $keys = array_column(Menu::shown(), 'key');

        $this->assertContains('designs', $keys);
        $this->assertContains('xonca', $keys, 'the xonça line stands before its designs are drawn');
        $this->assertNotContains('wrappings', $keys, 'no wrapping exists, so the page would be empty');

        // And the line that is not ready yet carries the owner's own word for it.
        $xonca = collect(Menu::shown())->firstWhere('key', 'xonca');
        $this->assertSame('Tezliklə', $xonca['badge']);

        $this->get(route('home'))->assertOk()->assertSee('Tezliklə')->assertSee(route('xonca.index'), false);
    }

    public function test_the_owner_hides_a_line_and_hangs_a_badge_on_another(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Pages\MenuSettings::class)
            ->set('data.rows', [
                ['key' => 'gifts', 'on' => true, 'badge' => 'Yeni'],
                ['key' => 'designs', 'on' => true, 'badge' => ''],
                ['key' => 'xonca', 'on' => false, 'badge' => 'Tezliklə'],
            ])
            ->call('save');

        $keys = array_column(Menu::shown(), 'key');
        $this->assertSame(['gifts', 'designs'], array_slice($keys, 0, 2), 'his order, not the code\'s');
        $this->assertNotContains('xonca', $keys);

        $page = $this->get(route('home'))->assertOk();
        $page->assertSee('Yeni');
        $page->assertDontSee(route('xonca.index'), false);
    }

    /** A page with nothing on it cannot be shown however the menu is set. */
    public function test_a_line_whose_page_is_empty_stays_out(): void
    {
        Setting::put(Setting::MENU, json_encode([
            'wrappings' => ['on' => true, 'badge' => '', 'order' => 0],
        ]));

        $this->assertNotContains('wrappings', array_column(Menu::shown(), 'key'));
        $this->assertNotNull(Menu::why('wrappings'));
    }

    public function test_the_xonca_page_says_the_designs_are_coming(): void
    {
        $this->get(route('xonca.index'))->assertOk()
            ->assertSee('Xonçaya kiçik şokoladlar')
            ->assertSee('Tezliklə burada olacaq');
    }
}
