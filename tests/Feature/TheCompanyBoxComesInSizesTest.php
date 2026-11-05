<?php

namespace Tests\Feature;

use App\Models\BoxSize;
use App\Models\CorporateRequest;
use App\Models\Setting;
use App\Support\CorporatePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The company box comes in sizes, and the company picks one.
 *
 * The size decides three things: the shape of the box drawn in the preview,
 * the picture behind it where the owner has rendered one, and the price. The
 * minimum order and the making time are the same whatever the size and stay
 * on the page's own settings, because that is what the owner said.
 *
 * The hard part is the day before he adds any size at all: the page must keep
 * the one size it has always had rather than losing its measurements because
 * a feature was added and nobody has used it yet.
 */
class TheCompanyBoxComesInSizesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::CORPORATE_ENABLED, '1');

        /* The owner's own three sizes are put in by a migration, so a fresh
           database already has them. This class is about the feature and not
           about his list - including the day before any size exists at all -
           so it starts from none. The list itself is checked in
           TheThreeSizesTheOwnerGaveTest. */
        BoxSize::query()->delete();
    }

    private function made(array $extra = []): BoxSize
    {
        return BoxSize::create($extra + [
            'name' => 'Mini',
            'dimensions' => '4,5 × 9 × 1,5 sm',
            'width_mm' => 45,
            'height_mm' => 90,
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    private function sharedLadder(array $steps): void
    {
        $saved = json_decode((string) Setting::get(Setting::CORPORATE), true) ?: [];
        Setting::put(Setting::CORPORATE, json_encode($saved + ['ladder' => $steps]));
    }

    // ------------------------------------------------------- with no sizes

    /** Before a single size exists the page is exactly what it was. */
    public function test_with_no_sizes_the_page_keeps_the_one_it_has_always_had(): void
    {
        $sizes = CorporatePage::sizes();

        $this->assertCount(1, $sizes);
        $this->assertNull($sizes[0]['id']);
        $this->assertSame(45, $sizes[0]['w']);
        $this->assertSame(90, $sizes[0]['h']);
        $this->assertSame(__(CorporatePage::DEFAULTS['size']), $sizes[0]['dimensions']);

        // And no picker: there is nothing to pick between.
        $this->get('/sirketler-ucun')->assertOk()->assertDontSee('id="co-sizes"', false);
    }

    public function test_one_size_is_shown_but_still_not_offered_as_a_choice(): void
    {
        $this->made(['name' => 'Yeganə']);

        $page = $this->get('/sirketler-ucun')->assertOk();

        $page->assertSee('4,5 × 9 × 1,5 sm');
        $page->assertDontSee('id="co-sizes"', false);
    }

    // ---------------------------------------------------------- the picker

    public function test_the_sizes_are_offered_in_the_owners_order(): void
    {
        $this->made(['name' => 'İkinci', 'sort_order' => 2]);
        $this->made(['name' => 'Birinci', 'sort_order' => 1]);

        $this->assertSame(['Birinci', 'İkinci'], array_column(CorporatePage::sizes(), 'title'));

        $page = $this->get('/sirketler-ucun')->assertOk();
        $page->assertSee('id="co-sizes"', false);
        $page->assertSee('Birinci');
        $page->assertSee('İkinci');
    }

    public function test_a_size_switched_off_is_not_offered(): void
    {
        $this->made(['name' => 'Gizli', 'is_active' => false]);
        $this->made(['name' => 'Açıq']);

        $this->assertSame(['Açıq'], array_column(CorporatePage::sizes(), 'title'));
    }

    /** The first size is what the page shows before anything is clicked. */
    public function test_the_first_size_is_the_one_the_page_opens_on(): void
    {
        $first = $this->made(['name' => 'Birinci', 'dimensions' => 'BİRİNCİ ÖLÇÜ', 'sort_order' => 1]);
        $this->made(['name' => 'İkinci', 'dimensions' => 'İKİNCİ ÖLÇÜ', 'sort_order' => 2]);

        $page = $this->get('/sirketler-ucun')->assertOk();

        // Both sizes' measurements are in the HTML; the chosen one is shown.
        $page->assertSee('<b data-size-text="0">BİRİNCİ ÖLÇÜ', false);
        $page->assertSee('<b data-size-text="1" hidden>İKİNCİ ÖLÇÜ', false);
        $page->assertSee('name="box_size_id" id="co-size-field" value="' . $first->id . '"', false);
    }

    // ----------------------------------------------------------- the price

    public function test_each_size_carries_its_own_ladder(): void
    {
        $this->made(['name' => 'Mini', 'sort_order' => 1, 'ladder' => [['from' => 100, 'price' => 1.2]]]);
        $this->made(['name' => 'Böyük', 'sort_order' => 2, 'ladder' => [['from' => 100, 'price' => 2.5]]]);

        $sizes = CorporatePage::sizes();

        $this->assertSame(1.2, $sizes[0]['ladder'][0]['price']);
        $this->assertSame(2.5, $sizes[1]['ladder'][0]['price']);
    }

    /** A size with no prices of its own shows the shop's, not nothing. */
    public function test_a_size_without_a_ladder_falls_back_to_the_shops(): void
    {
        $this->sharedLadder([['from' => 100, 'price' => 0.9]]);
        $size = $this->made();

        $this->assertSame(0.9, $size->ladderRows()[0]['price']);
    }

    public function test_the_price_quoted_is_the_price_of_the_size_asked_for(): void
    {
        $this->sharedLadder([['from' => 100, 'price' => 0.9]]);
        $big = $this->made(['name' => 'Böyük', 'ladder' => [['from' => 100, 'price' => 2.5]]]);

        $this->assertSame(2.5, CorporatePage::unitPriceFor(100, $big));
        $this->assertSame(250.0, CorporatePage::totalFor(100, $big));

        // With no size named, the shop's own ladder still answers.
        $this->assertSame(0.9, CorporatePage::unitPriceFor(100));
    }

    /**
     * Asking a size for its ladder must not ask the page for its sizes.
     *
     * The page builds the sizes, and a size with no ladder of its own wants
     * the page's: page -> sizes -> size -> page. Written the obvious way this
     * does not fail, it hangs, and the test that finds it is one that simply
     * loads the page.
     */
    public function test_a_size_asking_for_the_shared_ladder_does_not_loop(): void
    {
        $this->sharedLadder([['from' => 100, 'price' => 0.9]]);
        $this->made();

        $this->get('/sirketler-ucun')->assertOk();
        $this->assertSame(0.9, CorporatePage::sizes()[0]['ladder'][0]['price']);
    }

    // ------------------------------------------------------- the enquiry

    public function test_the_size_asked_for_is_kept_with_the_enquiry(): void
    {
        $size = $this->made(['name' => 'Böyük']);

        $this->post('/sirketler-ucun', [
            'company' => 'Kafe', 'phone' => '0501112233', 'quantity' => 100,
            'box_size_id' => $size->id,
        ])->assertRedirect();

        $this->assertSame($size->id, CorporateRequest::latest('id')->first()->box_size_id);
    }

    /** A size the shop does not sell cannot be attached to an enquiry. */
    public function test_a_hidden_size_cannot_be_asked_for(): void
    {
        $hidden = $this->made(['name' => 'Gizli', 'is_active' => false]);

        $this->post('/sirketler-ucun', [
            'company' => 'Kafe', 'phone' => '0501112233', 'quantity' => 100,
            'box_size_id' => $hidden->id,
        ])->assertSessionHasErrors('box_size_id');

        $this->assertSame(0, CorporateRequest::count());
    }

    public function test_an_enquiry_without_a_size_is_still_taken(): void
    {
        $this->post('/sirketler-ucun', [
            'company' => 'Kafe', 'phone' => '0501112233', 'quantity' => 100,
        ])->assertRedirect();

        $this->assertNull(CorporateRequest::latest('id')->first()->box_size_id);
    }

    // --------------------------------------------------------- the render

    /** The owner's render reaches the page, with the face he marked on it. */
    public function test_the_render_and_the_face_it_carries_reach_the_page(): void
    {
        $this->made([
            'name' => 'Render',
            'front_image' => 'box-sizes/front.png',
            'back_image' => 'box-sizes/back.png',
            'face_left' => 20, 'face_top' => 10, 'face_right' => 80, 'face_bottom' => 90,
        ]);

        $size = CorporatePage::sizes()[0];

        $this->assertStringContainsString('box-sizes/front.png', $size['front']);
        $this->assertStringContainsString('box-sizes/back.png', $size['back']);
        $this->assertSame(['l' => 0.2, 't' => 0.1, 'r' => 0.8, 'b' => 0.9], $size['face']);
    }

    /** With no render the page draws the box itself, as it always has. */
    public function test_a_size_without_a_render_says_so_rather_than_naming_a_missing_file(): void
    {
        $this->made();

        $size = CorporatePage::sizes()[0];

        $this->assertNull($size['front']);
        $this->assertNull($size['back']);
    }

    /**
     * Four numbers typed into a form can describe no rectangle at all.
     *
     * A right edge to the left of the left one would have the drawing paint
     * backwards — a logo mirrored across the box — rather than fail, so it
     * falls back to the whole picture.
     */
    public function test_a_face_that_is_not_a_rectangle_falls_back_to_the_whole_picture(): void
    {
        $size = $this->made(['face_left' => 80, 'face_right' => 20]);

        $this->assertSame([0.0, 0.0, 1.0, 1.0], $size->faceRect());

        $flat = $this->made(['name' => 'Yastı', 'face_top' => 50, 'face_bottom' => 50]);
        $this->assertSame([0.0, 0.0, 1.0, 1.0], $flat->faceRect());
    }

    // ------------------------------------------------------- the admin

    public function test_the_owner_edits_the_sizes_in_the_admin(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));
        $this->made(['name' => 'Mini']);

        $this->get('/admin/box-sizes')->assertOk()->assertSee('Mini');
        $this->get('/admin/box-sizes/create')->assertOk();
    }

    /**
     * The corporate page's own settings say where a size is really changed.
     *
     * «Ölçü» sits on that form and was the size for as long as there were no
     * sizes. The moment one exists the field is no longer read, and an owner
     * retyping it there would watch the page not change — so the field says
     * so, and the page carries a button across.
     */
    public function test_the_corporate_settings_point_at_the_sizes(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['is_admin' => true]));

        // With none, the field is still the size and says only what is next.
        $this->get('/admin/corporate')->assertOk()
            ->assertSee('Qutu ölçüləri')
            ->assertDontSee('Bu yazı artıq işlənmir');

        $this->made();

        $this->get('/admin/corporate')->assertOk()
            ->assertSee('Bu yazı artıq işlənmir', false)
            ->assertSee('/admin/box-sizes', false);
    }

    public function test_a_manager_does_not_get_into_the_sizes(): void
    {
        $this->actingAs(\App\Models\User::factory()->create([
            'is_admin' => false, 'role' => \App\Models\User::MANAGER,
        ]));

        $this->get('/admin/box-sizes')->assertForbidden();
    }
}
