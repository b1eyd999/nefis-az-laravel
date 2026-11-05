<?php

namespace Tests\Feature;

use App\Models\BoxSize;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three sizes the owner dictated, as the shop actually offers them.
 *
 * TheCompanyBoxComesInSizesTest is about the feature and clears the table to
 * test it from nothing. This is about his list: the measurements he gave,
 * their order, and the fact that they reach the page in all three languages.
 * Get a number wrong here and a company orders a box that is not the one it
 * saw, so the figures are written out rather than read from the migration.
 */
class TheThreeSizesTheOwnerGaveTest extends TestCase
{
    use RefreshDatabase;

    /** Width, height of the printed face in mm — and the depth in the words. */
    private const HIS = [
        ['Uzun', 85, 160, '8,5 × 16 × 1 sm'],
        ['Kvadrat', 85, 85, '8,5 × 8,5 × 1 sm'],
        ['Kiçik', 40, 60, '4 × 6 × 1,5 sm'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::CORPORATE_ENABLED, '1');
    }

    public function test_the_shop_offers_his_three_sizes_in_the_order_he_gave_them(): void
    {
        $sizes = BoxSize::offered()->get();

        $this->assertCount(3, $sizes);

        foreach (self::HIS as $i => [$name, $width, $height, $dimensions]) {
            $this->assertSame($name, $sizes[$i]->name);
            $this->assertSame($width, $sizes[$i]->width_mm);
            $this->assertSame($height, $sizes[$i]->height_mm);
            $this->assertSame($dimensions, $sizes[$i]->dimensions);
        }
    }

    /** Three sizes is more than one, so the page asks which. */
    public function test_the_page_lets_a_company_pick_between_them(): void
    {
        $page = $this->get('/sirketler-ucun')->assertOk();

        $page->assertSee('id="co-sizes"', false);

        foreach (self::HIS as [$name, , , $dimensions]) {
            $page->assertSee($name);
            // All three sets of measurements are in the HTML, not only the
            // chosen one: the script shows one of them, it writes none.
            $page->assertSee($dimensions);
        }

        // The first one is what the page opens on.
        $page->assertSee('<b data-size-text="0">' . self::HIS[0][3], false);
    }

    public function test_the_measurements_are_written_in_the_readers_own_language(): void
    {
        $this->get('/ru/sirketler-ucun')->assertOk()
            ->assertSee('Длинная')
            ->assertSee('8,5 × 16 × 1 см');

        $this->get('/en/sirketler-ucun')->assertOk()
            ->assertSee('Square')
            ->assertSee('8.5 × 8.5 × 1 cm');
    }

    /**
     * The example size that was put there from this side is gone.
     *
     * It was made an hour before he said what the real sizes are, to see the
     * admin screen working. Left in, the page would offer a fourth size the
     * shop does not sell.
     */
    public function test_the_example_size_is_not_offered_any_more(): void
    {
        $this->assertSame(0, BoxSize::where('name', 'Bir dilim')->count());
    }

    /**
     * The chip shows one set of measurements, not all three at once.
     *
     * Every size's line is in the HTML and the extra ones carry `hidden` —
     * but `.co-fact b` is display:block, which beats the browser's own
     * [hidden] rule. The same trap has been walked into twice elsewhere on
     * this site, which is why the rule is asserted rather than trusted.
     */
    public function test_the_lines_that_are_hidden_really_are_hidden(): void
    {
        $this->get('/sirketler-ucun')->assertOk()
            ->assertSee('.co-fact b[hidden]{ display:none; }', false);
    }

    /**
     * The example row goes only while it is still the example.
     *
     * It was put in from this side to see the admin screen working. Deleting
     * it by name alone would also take a size he had renamed to something
     * of his own and kept — so the wording has to match too.
     */
    public function test_an_example_size_he_has_edited_is_left_alone(): void
    {
        $untouched = BoxSize::create([
            'name' => 'Bir dilim',
            'dimensions' => '4,5 × 9 × 1,5 sm — bir dilim şokolad',
            'width_mm' => 45, 'height_mm' => 90,
        ]);
        $his = BoxSize::create([
            'name' => 'Bir dilim',
            'dimensions' => '4,5 × 9 sm — mənim ölçüm',
            'width_mm' => 45, 'height_mm' => 90,
        ]);

        $this->migration()->up();

        $this->assertNull($untouched->fresh());
        $this->assertNotNull($his->fresh());
    }

    /**
     * Said twice, it says the same thing.
     *
     * Not an idle worry: these are rows in a table the owner edits himself,
     * and a migration that inserted on every run would hand him duplicates
     * to delete — or, worse, undo the names and prices he had changed.
     */
    public function test_running_it_again_neither_duplicates_nor_overwrites(): void
    {
        $uzun = BoxSize::where('name', 'Uzun')->firstOrFail();
        $uzun->update(['dimensions' => 'MƏNİM ÖZ YAZIM', 'ladder' => [['from' => 100, 'price' => 1.4]]]);

        $this->migration()->up();

        $this->assertSame(3, BoxSize::count());

        $again = BoxSize::where('name', 'Uzun')->firstOrFail();
        $this->assertSame('MƏNİM ÖZ YAZIM', $again->dimensions);
        $this->assertSame(1.4, $again->ladderRows()[0]['price']);
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_11_05_090000_the_company_box_comes_in_three_sizes.php'
        );
    }
}
