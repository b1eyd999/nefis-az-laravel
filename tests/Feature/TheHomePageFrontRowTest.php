<?php

namespace Tests\Feature;

use App\Filament\Pages\HomeOrder;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The designs the owner puts at the front of the home page.
 *
 * Two things have to hold at once. What he picked leads, in his order — and
 * everything he did not pick keeps exactly the order it already had, which
 * is the half that would break quietly: a sort that is not stable reshuffles
 * fifty designs behind his back and nothing on the page says so.
 */
class TheHomePageFrontRowTest extends TestCase
{
    use RefreshDatabase;

    private function design(string $name, int $order = 0, array $extra = []): Product
    {
        return Product::create($extra + [
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'price' => 10,
            'sort_order' => $order,
        ]);
    }

    /** The names of the designs on the home page, in the order they are drawn. */
    private function asShown(): array
    {
        $page = $this->get('/');
        $page->assertOk();

        return $page->viewData('products')->pluck('name')->all();
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_without_a_pick_the_shelf_keeps_its_own_order(): void
    {
        $this->design('Alyonka', 1);
        $this->design('Kinder', 2);
        $this->design('Milka', 3);

        $this->assertSame(['Alyonka', 'Kinder', 'Milka'], $this->asShown());
    }

    public function test_what_he_picked_leads_in_his_order(): void
    {
        $alyonka = $this->design('Alyonka', 1);
        $this->design('Kinder', 2);
        $milka = $this->design('Milka', 3);

        Setting::put(Setting::HOME_FIRST, json_encode([$milka->id, $alyonka->id]));

        $this->assertSame(['Milka', 'Alyonka', 'Kinder'], $this->asShown());
    }

    /**
     * The designs he did not pick keep their order among themselves.
     *
     * Enough of them that an unstable sort cannot pass by luck.
     */
    public function test_everything_else_stays_where_it_was(): void
    {
        $names = [];

        for ($i = 1; $i <= 12; $i++) {
            $names[] = $name = 'Dizayn '.$i;
            $this->design($name, $i);
        }

        $last = Product::where('name', 'Dizayn 12')->first();
        Setting::put(Setting::HOME_FIRST, json_encode([$last->id]));

        $expected = array_merge(['Dizayn 12'], array_values(array_diff($names, ['Dizayn 12'])));

        $this->assertSame($expected, $this->asShown());
    }

    /** A design that is not on the public shelf cannot be pulled to the front. */
    public function test_a_hidden_design_in_the_list_changes_nothing(): void
    {
        $this->design('Alyonka', 1);
        $this->design('Kinder', 2);
        $hidden = $this->design('Gizli', 3, ['is_active' => false]);
        $company = $this->design('Şirkət', 4, ['business_only' => true]);

        Setting::put(Setting::HOME_FIRST, json_encode([$hidden->id, $company->id]));

        $this->assertSame(['Alyonka', 'Kinder'], $this->asShown());
    }

    /** An id of a design that no longer exists is simply not there. */
    public function test_a_deleted_design_leaves_no_gap(): void
    {
        $this->design('Alyonka', 1);
        $kinder = $this->design('Kinder', 2);

        Setting::put(Setting::HOME_FIRST, json_encode([9999, $kinder->id]));

        $this->assertSame(['Kinder', 'Alyonka'], $this->asShown());
    }

    /**
     * Rubbish in the row is read as an empty list, not as design number nought.
     *
     * The page survives either way — no design has id 0 — so this is asserted
     * on the list itself, where it means something.
     */
    public function test_rubbish_in_the_setting_is_read_as_nothing(): void
    {
        $this->design('Alyonka', 1);

        foreach (['not json', '{"a":1}', '["", null, "abc", 0, -3]', 'null', '[]'] as $rubbish) {
            Setting::put(Setting::HOME_FIRST, $rubbish);

            $this->assertSame([], Setting::homeFirst(), $rubbish);
            $this->assertSame(['Alyonka'], $this->asShown(), $rubbish);
        }
    }

    /** A design written down twice takes one place, not two. */
    public function test_the_list_is_read_without_repeats(): void
    {
        Setting::put(Setting::HOME_FIRST, json_encode([5, 5, 7, '5', 7]));

        $this->assertSame([5, 7], Setting::homeFirst());
    }

    public function test_the_owner_picks_the_order_in_the_admin(): void
    {
        $alyonka = $this->design('Alyonka', 1);
        $this->design('Kinder', 2);
        $milka = $this->design('Milka', 3);

        $this->admin();

        Livewire::test(HomeOrder::class)
            ->fillForm(['rows' => [['product_id' => $milka->id], ['product_id' => $alyonka->id]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([$milka->id, $alyonka->id], Setting::homeFirst());
        $this->assertSame(['Milka', 'Alyonka', 'Kinder'], $this->asShown());
    }

    /** Emptying the list gives the shelf back its plain order. */
    public function test_taking_everything_out_restores_the_plain_order(): void
    {
        $alyonka = $this->design('Alyonka', 1);
        $this->design('Kinder', 2);

        Setting::put(Setting::HOME_FIRST, json_encode([$alyonka->id]));
        $this->admin();

        Livewire::test(HomeOrder::class)
            ->fillForm(['rows' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], Setting::homeFirst());
        $this->assertSame(['Alyonka', 'Kinder'], $this->asShown());
    }

    /** What was saved is what the page shows when he opens it again. */
    public function test_the_page_opens_on_what_was_saved(): void
    {
        $alyonka = $this->design('Alyonka', 1);
        $milka = $this->design('Milka', 2);

        Setting::put(Setting::HOME_FIRST, json_encode([$milka->id, $alyonka->id]));
        $this->admin();

        $rows = Livewire::test(HomeOrder::class)->get('data')['rows'];

        $this->assertSame([$milka->id, $alyonka->id], array_column(array_values($rows), 'product_id'));
    }

    /**
     * A design picked and since hidden keeps its place in the admin.
     *
     * Dropping it from the list the moment it leaves the shelf would lose
     * his choice because he hid a design for an afternoon, so it stays and
     * says why it is not on the page.
     */
    public function test_a_picked_design_that_left_the_shelf_is_not_lost_from_the_admin(): void
    {
        $gone = $this->design('Gizli', 1, ['is_active' => false]);
        $this->design('Kinder', 2);

        Setting::put(Setting::HOME_FIRST, json_encode([$gone->id]));
        $this->admin();

        Livewire::test(HomeOrder::class)
            ->assertSee('saytda görünmür')
            ->assertSee('Gizli');
    }

    /**
     * A design that has been deleted outright opens no row at all.
     *
     * A row whose design cannot be named is an empty box the owner has to
     * work out and remove himself.
     */
    public function test_a_deleted_design_opens_no_row(): void
    {
        $kinder = $this->design('Kinder', 1);

        Setting::put(Setting::HOME_FIRST, json_encode([9999, $kinder->id]));
        $this->admin();

        $rows = Livewire::test(HomeOrder::class)->get('data')['rows'];

        $this->assertSame([$kinder->id], array_column(array_values($rows), 'product_id'));
    }

    /**
     * The same design cannot be put in the row twice.
     *
     * Two places taken by one box would push a different design out of the
     * front row, and the page would simply not have the design he expected.
     */
    public function test_the_same_design_cannot_be_added_twice(): void
    {
        $alyonka = $this->design('Alyonka', 1);
        $this->design('Kinder', 2);
        $this->admin();

        Livewire::test(HomeOrder::class)
            ->fillForm(['rows' => [['product_id' => $alyonka->id], ['product_id' => $alyonka->id]]])
            ->call('save')
            ->assertHasFormErrors();

        $this->assertSame([], Setting::homeFirst(), 'a repeated design was saved anyway');
    }

    /** Only the shop's own people arrange its front page. */
    public function test_a_customer_cannot_open_the_page(): void
    {
        $this->assertFalse(HomeOrder::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::CUSTOMER]));
        $this->assertFalse(HomeOrder::canAccess());

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        $this->assertTrue(HomeOrder::canAccess());
    }
}
