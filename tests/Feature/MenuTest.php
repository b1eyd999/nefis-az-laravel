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

        // It stands with its word on it, and does not open yet — the
        // designs are not drawn, so there is nothing behind the door.
        $this->assertFalse($xonca['link']);
        $this->get(route('home'))->assertOk()->assertSee('Tezliklə')->assertSee('Xonça və nişan');
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

    /**
     * The header bar alone.
     *
     * The footer prints the same lines with the same markup, so a test that
     * searches the whole page can pass on the footer while the bar is empty.
     */
    private function bar(string $html): string
    {
        $this->assertMatchesRegularExpression('~<nav class="primary"~', $html);
        preg_match('~<nav class="primary".*?</nav>~s', $html, $m);

        return $m[0];
    }

    /** A line standing on the bar in its own right, open or shut. */
    private function barHas(string $html, string $title): bool
    {
        return (bool) preg_match(
            '~<(a|span) class="nav-top[^"]*"[^>]*>' . preg_quote($title, '~') . '~',
            $this->bar($html)
        );
    }

    /** …and the same line actually opening its page. */
    private function barLinks(string $html, string $url): bool
    {
        return (bool) preg_match(
            '~<a class="nav-top" href="' . preg_quote($url, '~') . '"~',
            $this->bar($html)
        );
    }

    /** A line inside the «Məhsullar» list. */
    private function listHas(string $html, string $url): bool
    {
        return (bool) preg_match(
            '~<a class="nav-item" href="' . preg_quote($url, '~') . '"~',
            $this->bar($html)
        );
    }

    public function test_the_xonca_line_stands_on_the_bar_rather_than_inside_the_list(): void
    {
        $xonca = route('xonca.index');
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertTrue($this->barHas($html, 'Xonça və nişan'), 'xonça is a word of its own on the bar');
        $this->assertFalse($this->listHas($html, $xonca), 'and not also inside «Məhsullar»');

        // The badge travels with it.
        $this->assertMatchesRegularExpression(
            '~<(a|span) class="nav-top[^"]*"[^>]*>Xonça və nişan<i class="ni-badge">Tezliklə</i>~',
            $this->bar($html)
        );

        // Everything else is still under the one word.
        $this->assertTrue($this->listHas($html, route('designs.index')));
        $this->assertFalse($this->barHas($html, 'Dizaynlar'));
    }

    public function test_the_owner_moves_a_line_between_the_bar_and_the_list(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Xonça back into the list, corporate out onto the bar.
        Livewire::test(\App\Filament\Pages\MenuSettings::class)
            ->set('data.rows', [
                ['key' => 'designs', 'on' => true, 'badge' => '', 'place' => 'drop', 'link' => true],
                ['key' => 'xonca', 'on' => true, 'badge' => 'Tezliklə', 'place' => 'drop', 'link' => true],
                ['key' => 'corporate', 'on' => true, 'badge' => 'Yeni', 'place' => 'top', 'link' => true],
            ])
            ->call('save');

        // A key he never sent keeps the place the code gave it, the way `on`
        // and the badge already do.
        $this->assertSame(['corporate'], array_column(Menu::shownIn('top'), 'key'));
        $drop = array_column(Menu::shownIn('drop'), 'key');
        $this->assertContains('xonca', $drop);
        $this->assertContains('designs', $drop);
        $this->assertNotContains('corporate', $drop);

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertTrue($this->barLinks($html, route('corporate.index')));
        $this->assertTrue($this->listHas($html, route('xonca.index')));
        $this->assertFalse($this->barHas($html, 'Xonça və nişan'));
    }

    /** A place nobody has heard of puts the line back where it is safe. */
    public function test_a_nonsense_place_falls_back_to_the_list(): void
    {
        Setting::put(Setting::MENU, json_encode([
            'designs' => ['on' => true, 'badge' => '', 'place' => 'everywhere', 'order' => 0],
        ]));

        $this->assertSame('drop', collect(Menu::shown())->firstWhere('key', 'designs')['place']);
    }

    /** With one line left in it, «Məhsullar» stops being a list. */
    public function test_a_single_remaining_line_becomes_a_plain_link(): void
    {
        $rows = [];
        foreach (array_keys(Menu::ENTRIES) as $key) {
            $rows[$key] = [
                'on' => true,
                'badge' => '',
                'place' => $key === 'designs' ? 'drop' : 'top',
                'order' => count($rows),
            ];
        }
        Setting::put(Setting::MENU, json_encode($rows));

        $bar = $this->bar($this->get(route('home'))->assertOk()->getContent());
        $this->assertStringNotContainsString('class="nav-item" href="' . route('designs.index') . '"', $bar);
        $this->assertStringContainsString('<a href="' . route('designs.index') . '">Dizaynlar</a>', $bar);
    }

    /** And it keeps its badge there, the way it had one inside the list. */
    public function test_the_lone_remaining_line_keeps_its_badge(): void
    {
        $rows = [];
        foreach (array_keys(Menu::ENTRIES) as $key) {
            $rows[$key] = [
                'on' => true,
                'badge' => $key === 'designs' ? 'Yeni' : '',
                'place' => $key === 'designs' ? 'drop' : 'top',
                'order' => count($rows),
            ];
        }
        Setting::put(Setting::MENU, json_encode($rows));

        $this->assertStringContainsString(
            '<a href="' . route('designs.index') . '">Dizaynlar<i class="ni-badge">Yeni</i></a>',
            $this->bar($this->get(route('home'))->assertOk()->getContent())
        );
    }

    /**
     * The path production actually takes: the owner arranged this menu before
     * a line could stand on the bar, so his saved JSON has no `place` in it
     * at all, and the code's own default is what puts xonça up there.
     */
    public function test_a_menu_saved_before_places_existed_still_puts_xonca_on_the_bar(): void
    {
        $rows = [];
        $at = 0;
        foreach (array_keys(Menu::ENTRIES) as $key) {
            $rows[$key] = ['on' => true, 'badge' => $key === 'xonca' ? 'Tezliklə' : '', 'order' => $at++];
        }
        Setting::put(Setting::MENU, json_encode($rows, JSON_UNESCAPED_UNICODE));

        $this->assertSame(['xonca'], array_column(Menu::shownIn('top'), 'key'));

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertTrue($this->barHas($html, 'Xonça və nişan'));
        $this->assertFalse($this->listHas($html, route('xonca.index')));
    }

    /** And saving it again from anywhere does not quietly pull it back down. */
    public function test_saving_a_row_that_says_nothing_about_its_place_leaves_it_where_it_is(): void
    {
        $this->assertSame('top', collect(Menu::shown())->firstWhere('key', 'xonca')['place']);

        Menu::save([
            ['key' => 'designs', 'on' => true, 'badge' => ''],
            ['key' => 'xonca', 'on' => true, 'badge' => 'Tezliklə'],
        ]);

        $this->assertSame('top', collect(Menu::shown())->firstWhere('key', 'xonca')['place']);
    }

    /** The drawer files a promoted line outside the «Məhsullar» group too. */
    public function test_the_phone_drawer_takes_a_promoted_line_out_of_the_products_group(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // The drawer is the only place that prints the icon beside the name.
        $this->assertSame(1, substr_count($html, '💍 Xonça və nişan'),
            'the phone drawer still lists it exactly once');

        preg_match('~<span class="mn-head">Məhsullar</span>(.*?)</div>~s', $html, $m);
        $this->assertNotEmpty($m, 'the drawer still groups the products');
        $this->assertStringNotContainsString('Xonça və nişan', $m[1],
            'but not under the heading it was pulled out of');
        $this->assertStringContainsString(route('designs.index'), $m[1]);
    }

    /**
     * «Tezliklə» on a line that still opens the page is half a promise: the
     * visitor reads "soon", presses it anyway and lands on an empty page.
     */
    public function test_a_line_can_be_a_word_without_a_door_behind_it(): void
    {
        $bar = $this->bar($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringContainsString(
            '<span class="nav-top is-shut" aria-disabled="true">Xonça və nişan<i class="ni-badge">Tezliklə</i></span>',
            $bar
        );
        $this->assertFalse($this->barLinks($bar, route('xonca.index')), 'nothing to press');

        // The page itself is still there for anyone who knows the address.
        $this->get(route('xonca.index'))->assertOk();
    }

    public function test_the_owner_opens_the_door_when_the_designs_are_drawn(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Pages\MenuSettings::class)
            ->set('data.rows', [
                ['key' => 'xonca', 'on' => true, 'badge' => '', 'place' => 'top', 'link' => true],
            ])
            ->call('save');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertTrue($this->barLinks($html, route('xonca.index')));
        $this->assertStringNotContainsString('nav-top is-shut', $this->bar($html));
    }

    /** The same inside the «Məhsullar» list, where a line has a note too. */
    public function test_a_shut_line_inside_the_list_is_not_a_link_either(): void
    {
        $rows = [];
        foreach (array_keys(Menu::ENTRIES) as $key) {
            $rows[$key] = [
                'on' => true,
                // «Dizaynlar» is the one line that is ready in every test.
                'badge' => $key === 'designs' ? 'Tezliklə' : '',
                'place' => 'drop',
                'link' => $key !== 'designs',
                'order' => count($rows),
            ];
        }
        Setting::put(Setting::MENU, json_encode($rows));

        $bar = $this->bar($this->get(route('home'))->assertOk()->getContent());
        $this->assertStringContainsString('class="nav-item is-shut"', $bar);
        $this->assertFalse($this->listHas($bar, route('designs.index')));
        // Its neighbours are untouched.
        $this->assertTrue($this->listHas($bar, route('xonca.index')));
    }

    /** A save that says nothing about the door leaves it as it was. */
    public function test_saving_without_the_link_field_leaves_the_door_as_it_was(): void
    {
        $this->assertFalse(collect(Menu::shown())->firstWhere('key', 'xonca')['link']);

        Menu::save([['key' => 'xonca', 'on' => true, 'badge' => 'Tezliklə']]);

        $this->assertFalse(collect(Menu::shown())->firstWhere('key', 'xonca')['link']);
    }
}
