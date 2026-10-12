<?php

namespace Tests\Feature;

use App\Models\Chocolate;
use App\Models\DeliveryMethod;
use App\Models\Material;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wrapping;
use App\Support\Accounting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The wrapping comes off the shelf when somebody asks to be wrapped.
 *
 * Every material used to be spent «per box»: the order was counted in boxes
 * and each material's `per_box` multiplied by that. True of the box paper, the
 * glue and the print — false of the wrapping, which most customers do not buy.
 * The owner put it in one sentence: the wrapping has to go down when a
 * customer orders a wrapping, any wrapping. A ribbon goes further still — only
 * the roll the chosen wrap is tied to is cut.
 */
class WrapComesOutOfTheWrapTest extends TestCase
{
    use RefreshDatabase;

    private function paper(): Material
    {
        return Material::where('name', 'Qutu kağızı')->firstOrFail();
    }

    private function wrapPaper(): Material
    {
        return Material::where('name', 'Bükmə kağızı')->firstOrFail();
    }

    private function ribbon(string $name, int $onHand = 100): Material
    {
        return Material::create([
            'name' => $name, 'unit' => 'qutuluq', 'pack_price' => 10, 'pack_size' => 100,
            'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => $onHand, 'is_active' => true,
        ]);
    }

    private function wrapping(string $name, ?Material $ribbon = null): Wrapping
    {
        return Wrapping::create([
            'name' => $name, 'pattern' => 'wrappings/' . strtolower($name) . '.png', 'price' => 2,
            'ribbon' => Wrapping::SATIN, 'ribbon_color' => '#ff3434', 'pattern_scale' => 0.5,
            'ribbon_material_id' => $ribbon?->id, 'is_active' => true,
        ]);
    }

    /** `$boxes` boxes, wrapped in `$wrap` if one is given. */
    private function order(int $boxes, ?Wrapping $wrap = null): Order
    {
        $box = Product::create(['name' => 'Test', 'slug' => 'test-' . uniqid(), 'is_active' => true, 'price' => 4,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $bar = Chocolate::firstOrCreate(['name' => 'Milka 90 q'], ['base_price' => 4.50, 'sale_price' => 2.69]);
        $door = DeliveryMethod::where('type', 'door')->first();

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('cart.add'), array_filter([
            'product_id' => $box->id, 'chocolate_id' => $bar->id, 'quantity' => $boxes,
            'wrapping_id' => $wrap?->id,
        ]))->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('checkout.store'), [
            'delivery_method_id' => $door->id, 'contact_phone' => '1', 'delivery_address' => 'Bakı, Nizami küç. 5',
        ])->assertRedirect(route('orders.index'));

        return Order::latest('id')->firstOrFail();
    }

    public function test_a_plain_box_takes_no_wrapping_paper(): void
    {
        Accounting::purchase($this->paper(), 1, 14.00);
        Accounting::purchase($this->wrapPaper(), 1, 8.00);

        $this->order(3);

        $this->assertSame(47.0, $this->paper()->fresh()->stock, 'three sheets of box paper');
        $this->assertSame(500.0, $this->wrapPaper()->fresh()->stock, 'and not one sheet of wrapping');
    }

    public function test_a_wrapped_box_takes_one_sheet_of_wrapping_each(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);

        $order = $this->order(3, $this->wrapping('Qırmızı'));

        $this->assertSame(497.0, $this->wrapPaper()->fresh()->stock);
        // 3 × (0.280 box paper + 0.105 ink + 0.016 glue + 0.016 wrapping)
        $this->assertEqualsWithDelta(1.25, $order->materials_cost, 0.005);
    }

    public function test_only_the_ribbon_the_wrap_names_is_cut(): void
    {
        $red = $this->ribbon('Qırmızı lent');
        $white = $this->ribbon('Ağ lent');

        $this->order(2, $this->wrapping('Qırmızı', $red));

        $this->assertSame(98.0, $red->fresh()->stock);
        $this->assertSame(100.0, $white->fresh()->stock, 'the other roll is untouched');
    }

    public function test_a_wrap_tied_to_no_ribbon_takes_none(): void
    {
        $red = $this->ribbon('Qırmızı lent');

        $this->order(2, $this->wrapping('Lentsiz sayılan'));

        $this->assertSame(100.0, $red->fresh()->stock);
    }

    public function test_editing_the_order_moves_only_the_difference(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);
        $red = $this->ribbon('Qırmızı lent');

        $order = $this->order(2, $this->wrapping('Qırmızı', $red));
        $this->assertSame(498.0, $this->wrapPaper()->fresh()->stock);
        $this->assertSame(98.0, $red->fresh()->stock);

        // A third box on the same line: one more sheet, one more box's ribbon.
        $order->items->first()->update(['quantity' => 3]);
        Accounting::resync($order->fresh());

        $this->assertSame(497.0, $this->wrapPaper()->fresh()->stock);
        $this->assertSame(97.0, $red->fresh()->stock);

        // The customer thinks better of the wrap: both come straight back, and
        // the box paper stays out because the boxes are still being made.
        $order->items->first()->update(['wrapping_id' => null, 'wrapping_name' => null, 'wrapping_price' => null]);
        Accounting::resync($order->fresh());

        $this->assertSame(500.0, $this->wrapPaper()->fresh()->stock);
        $this->assertSame(100.0, $red->fresh()->stock);
        $this->assertSame(3, (int) $order->fresh()->items->first()->quantity);
    }

    public function test_cancelling_puts_the_wrapping_and_the_ribbon_back(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);
        $red = $this->ribbon('Qırmızı lent');

        $order = $this->order(2, $this->wrapping('Qırmızı', $red));
        $order->update(['status' => 'cancelled']);

        $this->assertSame(500.0, $this->wrapPaper()->fresh()->stock);
        $this->assertSame(100.0, $red->fresh()->stock);
    }

    public function test_the_per_box_figure_leaves_the_wrap_out(): void
    {
        $this->ribbon('Qırmızı lent');

        // A plain box: paper, ink, glue. Not the wrapping, not a ribbon.
        $this->assertEqualsWithDelta(0.401, Material::costOfOneBox(), 0.0001);
        $this->assertEqualsWithDelta(0.016, Material::costOfOneWrap(), 0.0001);
    }

    /**
     * A wrap sold on its own is still a wrap.
     *
     * The owner adds one by hand from the order page — a line with no box on
     * it, which the form names «Qablaşdırma: …» for him. The customer pays for
     * it, so the paper and the ribbon have to leave the shelf. Counting wraps
     * off the same filtered set as boxes would have missed exactly this line.
     */
    public function test_a_wrap_added_as_a_line_of_its_own_still_comes_off_the_shelf(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);
        $red = $this->ribbon('Qırmızı lent');
        $wrap = $this->wrapping('Qırmızı', $red);

        $order = $this->order(1);                       // one plain box
        $this->assertSame(500.0, $this->wrapPaper()->fresh()->stock);

        $order->items()->create([
            'product_name' => 'Qablaşdırma: Qırmızı', 'quantity' => 2, 'price' => 0,
            'wrapping_id' => $wrap->id, 'wrapping_name' => $wrap->name, 'wrapping_price' => $wrap->price,
        ]);
        Accounting::resync($order->fresh());

        $this->assertSame(498.0, $this->wrapPaper()->fresh()->stock, 'two wraps, two sheets');
        $this->assertSame(98.0, $red->fresh()->stock);
        // And the box paper still counts boxes only: the wrap line has none.
        $this->assertSame(1.0, round($order->fresh()->items->whereNotNull('product_id')->sum('quantity')));
    }

    /**
     * What the order took is what the order took.
     *
     * The wrap's name and price are frozen onto the line when it is sold, and
     * the roll its ribbon came off is frozen with them. Otherwise re-pointing
     * a wrap at another colour — which the owner will do the first time he
     * tidies the page — would rewrite what orders placed weeks ago took.
     */
    public function test_repointing_a_wrap_does_not_rewrite_an_old_order(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);
        $red = $this->ribbon('Qırmızı lent');
        $white = $this->ribbon('Ağ lent');
        $wrap = $this->wrapping('Qırmızı', $red);

        $order = $this->order(2, $wrap);
        $this->assertSame(98.0, $red->fresh()->stock);

        // He decides this paper looks better with white, and later adds a box.
        $wrap->update(['ribbon_material_id' => $white->id]);
        $order->items->first()->update(['quantity' => 3]);
        Accounting::resync($order->fresh());

        $this->assertSame(97.0, $red->fresh()->stock, 'the order goes on using the roll it was tied with');
        $this->assertSame(100.0, $white->fresh()->stock, 'and never touches the new one');
    }

    public function test_deleting_a_wrap_does_not_hand_its_paper_back(): void
    {
        Accounting::purchase($this->wrapPaper(), 1, 8.00);
        $red = $this->ribbon('Qırmızı lent');
        $wrap = $this->wrapping('Qırmızı', $red);

        $order = $this->order(2, $wrap);
        $this->assertSame(498.0, $this->wrapPaper()->fresh()->stock);

        /* Deleting the wrap empties `wrapping_id` on every line that ever used
           it — the name it was sold under is what survives, and what the
           warehouse counts. Two boxes were wrapped and stay wrapped. */
        $wrap->delete();
        Accounting::resync($order->fresh());

        $this->assertSame(498.0, $this->wrapPaper()->fresh()->stock);
        $this->assertSame(98.0, $red->fresh()->stock);
    }

    public function test_a_wrap_with_no_ribbon_on_it_cuts_none(): void
    {
        $red = $this->ribbon('Qırmızı lent');

        // Pointed at a roll, but the wrap itself carries no ribbon. The form
        // hides that field rather than clearing it, so the model has to.
        $bare = $this->wrapping('Lentsiz', $red);
        $bare->update(['ribbon' => Wrapping::NONE]);

        $this->order(2, $bare);

        $this->assertSame(100.0, $red->fresh()->stock);
    }

    public function test_a_roll_nobody_has_priced_yet_is_not_an_alarm(): void
    {
        $fresh = Material::create(['name' => 'Yaşıl lent', 'unit' => 'qutuluq', 'pack_price' => 0,
            'pack_size' => 100, 'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => 0]);

        $this->assertTrue($fresh->isNew());
        $this->assertFalse($fresh->isLow(), 'a roll in his drawer is not «bitib»');

        // Once he writes what it cost, it answers like everything else.
        Accounting::purchase($fresh, 1, 20.00);
        $this->assertFalse($fresh->fresh()->isNew());
        $this->assertFalse($fresh->fresh()->isLow());
        Accounting::adjust($fresh->fresh(), 0);
        $this->assertTrue($fresh->fresh()->isLow());
    }

    /**
     * Knowing what a roll costs is not the same as having one.
     *
     * The owner gave the price the day after the ribbons appeared: 1.20 ₼ a
     * roll, thirty wrapped boxes to a roll. The price is the recipe; the count
     * is the shelf; only the shelf can run out. Tying the two together would
     * have turned six priced rolls into six «bitib» the morning after.
     */
    public function test_knowing_what_a_roll_costs_is_not_the_same_as_having_one(): void
    {
        $red = Material::create(['name' => 'Qırmızı lent', 'unit' => 'bükməlik', 'pack_price' => 1.20,
            'pack_size' => 30, 'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => 0]);

        $this->assertTrue($red->isNew(), 'priced, but never counted');
        $this->assertFalse($red->isLow());
        $this->assertEqualsWithDelta(0.04, $red->costPerBox(), 0.0001);   // 1.20 / 30
        $this->assertEqualsWithDelta(0.04, Material::costOfRibbon()[0], 0.0001);

        // One count, and the warehouse starts answering for it like anything else.
        Accounting::adjust($red, 2);
        $this->assertFalse($red->fresh()->isNew());
        $this->assertFalse($red->fresh()->isLow());

        Accounting::adjust($red->fresh(), 0);
        $this->assertTrue($red->fresh()->isLow(), 'counted, and now really out');
    }

    /**
     * Counting an empty shelf is still counting it.
     *
     * A «Sayım» that matches the books writes no movement, on purpose — a line
     * in the ledger saying nothing. So «how much is there» cannot be read off
     * the ledger: a man who counts his red ribbon, finds none and types 0 did
     * answer the question, and the warehouse owes him «bitib» from then on.
     * That is why the hour he looked is a column of its own.
     */
    public function test_counting_an_empty_shelf_is_still_counting_it(): void
    {
        $red = Material::create(['name' => 'Qırmızı lent', 'unit' => 'bükməlik', 'pack_price' => 1.20,
            'pack_size' => 30, 'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => 0]);

        $this->assertTrue($red->isNew());

        $this->assertNull(Accounting::adjust($red, 0), 'a count that matches writes no movement');

        $this->assertNotNull($red->fresh()->counted_at);
        $this->assertFalse($red->fresh()->isNew());
        $this->assertTrue($red->fresh()->isLow(), 'counted, and honestly out');
    }

    public function test_the_migration_writes_the_price_he_gave_and_leaves_his_own_alone(): void
    {
        $seeded = Material::create(['name' => 'Ağ lent', 'unit' => 'qutuluq', 'pack_price' => 0,
            'pack_size' => 100, 'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => 0]);
        $his = Material::create(['name' => 'Qızıl lent', 'unit' => 'bükməlik', 'pack_price' => 4.50,
            'pack_size' => 25, 'per_box' => 1, 'spent_on' => Material::RIBBON, 'stock' => 0]);

        (require database_path('migrations/2026_10_12_090000_what_a_roll_of_ribbon_costs.php'))->up();

        $seeded->refresh();
        $this->assertSame(1.20, $seeded->pack_price);
        $this->assertSame(30.0, $seeded->pack_size);
        $this->assertSame('bükməlik', $seeded->unit);

        $his->refresh();
        $this->assertSame(4.50, $his->pack_price, 'a roll he priced himself is his own figure');
        $this->assertSame(25.0, $his->pack_size);

        // And the wrapping paper, which is not a ribbon, is not touched.
        $this->assertSame(8.0, $this->wrapPaper()->fresh()->pack_price);
    }

    public function test_a_switched_off_material_is_not_in_the_warehouse_list(): void
    {
        $gone = Material::create(['name' => ' A4 ', 'unit' => 'ədəd', 'pack_price' => 9, 'pack_size' => 500,
            'per_box' => 1, 'stock' => 323, 'is_active' => true]);

        // The owner typed the name himself, so the migration does not insist
        // on the case or the spaces around it.
        (require database_path('migrations/2026_10_11_160000_the_wrap_comes_out_of_the_wrap.php'))->up();
        $this->assertFalse($gone->fresh()->is_active);
        $this->assertSame(323.0, $gone->fresh()->stock, 'nothing of his is thrown away');

        $owner = User::factory()->create(['role' => User::ADMIN]);
        $this->actingAs($owner)->get('/admin-phone/anbar')->assertOk()->assertDontSee('A4');
        \Livewire\Livewire::test(\App\Filament\Resources\MaterialResource\Pages\ListMaterials::class)
            ->assertCanNotSeeTableRecords([$gone])
            ->assertCanSeeTableRecords([$this->paper()]);
    }

    /**
     * The ribbons the shop opens the page with are its own.
     *
     * These are the ten wraps and the exact colours the shop had on the day
     * the owner asked for ribbons in the warehouse. He should not have to type
     * six rows and tie ten wraps to them by hand, so the migration reads the
     * colours off the wraps: two shades of the same red are one roll, every
     * twine is one «Kəndir», and a wrap with no colour of its own gets none.
     */
    public function test_the_migration_makes_one_ribbon_per_colour_the_shop_uses(): void
    {
        $his = [
            ['Pink Overlay', Wrapping::SATIN, '#eadeff'],
            ['Love HOOK', Wrapping::SATIN, '#ff3434'],
            ['Gift Overlay', Wrapping::SATIN, '#ff3939'],
            ['Gift Overlay BOX', Wrapping::SATIN, '#ffffff'],
            ['Gift Overlay PURPLE', Wrapping::SATIN, '#F3D3B4'],
            ['KRAFT HEARD', Wrapping::TWINE, '#5e3a15'],
            ['RED Overlay', Wrapping::SATIN, '#ffffff'],
            ['KRAT', Wrapping::TWINE, '#613f1d'],
            ['GIFTS OVERLAY', Wrapping::TWINE, '#523314'],
            ['DARK PURPLE', Wrapping::SATIN, '#4f1761'],
        ];

        foreach ($his as [$name, $ribbon, $colour]) {
            Wrapping::create(['name' => $name, 'pattern' => 'wrappings/x.png', 'price' => 2,
                'ribbon' => $ribbon, 'ribbon_color' => $colour, 'pattern_scale' => 0.5]);
        }

        (require database_path('migrations/2026_10_11_160000_the_wrap_comes_out_of_the_wrap.php'))->up();

        $rolls = Material::where('spent_on', Material::RIBBON)->orderBy('name')->pluck('name')->all();
        $this->assertSame(
            ['Açıq bənövşəyi lent', 'Ağ lent', 'Bej lent', 'Bənövşəyi lent', 'Kəndir', 'Qırmızı lent'],
            $rolls
        );

        // Two reds, one roll; three twines, one «Kəndir»; every wrap tied.
        $this->assertSame(2, Material::where('name', 'Qırmızı lent')->firstOrFail()->wrappings()->count());
        $this->assertSame(3, Material::where('name', 'Kəndir')->firstOrFail()->wrappings()->count());
        $this->assertSame(0, Wrapping::whereNull('ribbon_material_id')->count());

        // Nothing is claimed to be on the shelf, and nothing is claimed about
        // the price: he writes both with «Alış».
        $red = Material::where('name', 'Qırmızı lent')->firstOrFail();
        $this->assertSame(0.0, $red->stock);
        $this->assertSame(0.0, $red->pack_price);

        // And run twice it makes nothing twice.
        (require database_path('migrations/2026_10_11_160000_the_wrap_comes_out_of_the_wrap.php'))->up();
        $this->assertSame(6, Material::where('spent_on', Material::RIBBON)->count());
    }

    public function test_the_warehouse_screens_say_what_each_material_is_spent_on(): void
    {
        $red = $this->ribbon('Qırmızı lent');
        $this->wrapping('Qırmızı', $red);
        $owner = User::factory()->create(['role' => User::ADMIN]);

        $phone = $this->actingAs($owner)->get('/admin-phone/anbar')->assertOk();
        $phone->assertSee('hər bükməyə');          // the wrapping paper
        $phone->assertSee('1 qablaşdırmada');      // the ribbon, and how many wraps use it
        $phone->assertSee('hər qutuya');           // the box paper

        /* The two figures a wrapped box costs, said apart: 0.401 ₼ of box, and
           0.016 of paper plus 0.10 of this test's ribbon on top of it. */
        $phone->assertSee('0.40 ₼', false);
        $phone->assertSee('bükülürsə +0.12 ₼', false);

        $this->actingAs($owner)->get('/admin/materials')->assertOk()->assertSee('Hər bükməyə');
    }
}
