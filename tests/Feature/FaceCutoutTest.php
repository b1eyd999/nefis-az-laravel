<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Designs where the customer's face sits on someone else's body: the slot
 * says the head has to be cut out of the photo, and the page then brings in
 * the cutter that does it in the visitor's own browser.
 */
class FaceCutoutTest extends TestCase
{
    use RefreshDatabase;

    private function box(bool $cutout, string $shape = 'rectangle'): Product
    {
        $box = Product::create(['name' => 'Pampers', 'slug' => 'pampers', 'is_active' => true, 'price' => 4.90,
            'template_width' => 3508, 'template_height' => 2480]);
        $box->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 3508, 'height' => 2480, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $box->photoSlots()->create(['label' => 'Üz', 'x' => 2014, 'y' => 1225, 'width' => 428, 'height' => 488,
            'rotation' => 0, 'shape' => $shape, 'cutout' => $cutout, 'sort_order' => 0]);

        return $box;
    }

    /** A second photo window on the same box, so a design can be half face and half picture. */
    private function addSlot(Product $box, bool $cutout): void
    {
        $box->photoSlots()->create(['label' => 'İkinci şəkil', 'x' => 700, 'y' => 700, 'width' => 400, 'height' => 400,
            'rotation' => 0, 'shape' => 'rectangle', 'cutout' => $cutout, 'sort_order' => 1]);
    }

    /** The switch lives on the owner's own page, so these tests sign in as him. */
    private function panel(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function edit(Product $box): Testable
    {
        return Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()]);
    }

    public function test_a_cutout_slot_brings_the_cutter_to_the_page(): void
    {
        $this->box(true);

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertSee('js/face-cutout.js', false)
            // the face has to be found before it can be framed
            ->assertSee('face-api.min.js', false)
            ->assertSee('"cutout":true', false);
    }

    public function test_an_ordinary_slot_loads_nothing_extra(): void
    {
        $this->box(false);

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertDontSee('js/face-cutout.js', false)
            ->assertDontSee('face-api.min.js', false)
            ->assertSee('"cutout":false', false);
    }

    public function test_an_oval_slot_still_looks_for_a_face_without_cutting(): void
    {
        $this->box(false, 'ellipse');

        $this->get(route('products.customize', 'pampers'))->assertOk()
            ->assertSee('face-api.min.js', false)
            ->assertDontSee('js/face-cutout.js', false);
    }

    public function test_the_switch_in_the_panel_reads_the_boxs_own_photo_windows(): void
    {
        $this->panel();

        $plain = $this->box(false);
        $this->edit($plain)->assertFormSet(['face_cutout' => false]);

        $plain->photoSlots()->update(['cutout' => true]);
        $this->edit($plain)->assertFormSet(['face_cutout' => true]);
    }

    public function test_turning_it_on_marks_every_photo_window_of_that_box(): void
    {
        $this->panel();
        $box = $this->box(false);
        $this->addSlot($box, false);

        $this->edit($box)->fillForm(['face_cutout' => true])->call('save')->assertHasNoFormErrors();

        $this->assertSame([true, true], $box->photoSlots()->pluck('cutout')->map(fn ($c) => (bool) $c)->all());
        $this->get(route('products.customize', 'pampers'))->assertSee('js/face-cutout.js', false);

        // The list of designs says which ones cut faces; the row comes from the
        // list's own query, which is where the marked windows are counted.
        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords([$box])
            ->assertTableColumnStateSet('face_cutout_slots_count', true, (string) $box->getKey());
    }

    public function test_turning_it_off_clears_them_again(): void
    {
        $this->panel();
        $box = $this->box(true);
        $this->addSlot($box, true);

        $this->edit($box)->fillForm(['face_cutout' => false])->call('save')->assertHasNoFormErrors();

        $this->assertSame([false, false], $box->photoSlots()->pluck('cutout')->map(fn ($c) => (bool) $c)->all());
        $this->get(route('products.customize', 'pampers'))->assertDontSee('js/face-cutout.js', false);
    }

    public function test_a_box_that_is_half_face_keeps_its_mix_until_the_switch_is_moved(): void
    {
        $this->panel();
        $box = $this->box(true);
        $this->addSlot($box, false);

        // Saving something else on the page must not answer for the box editor.
        $this->edit($box)->fillForm(['tag' => 'Populyar'])->call('save')->assertHasNoFormErrors();

        $this->assertSame([true, false], $box->photoSlots()->pluck('cutout')->map(fn ($c) => (bool) $c)->all());
        $this->assertSame('Populyar', $box->fresh()->tag);
    }

    public function test_a_save_that_never_touched_the_switch_leaves_the_windows_alone(): void
    {
        $this->panel();
        $box = $this->box(false);

        // The owner opens the design's page…
        $page = $this->edit($box);

        // …then marks the window in the box editor, in another tab…
        $box->photoSlots()->update(['cutout' => true]);

        // …and comes back to fix a word, without going near the switch.
        $page->fillForm(['name' => 'Pampers zarafat'])->call('save')->assertHasNoFormErrors();

        $this->assertTrue((bool) $box->photoSlots()->value('cutout'), 'the box editor’s choice survived');
    }

    public function test_the_switch_reaches_the_windows_of_older_designs_angles(): void
    {
        $this->panel();
        $box = $this->box(false);
        $angle = $box->angles()->create(['label' => 'Yan görünüş', 'template_image' => 'boxes/yan.webp',
            'template_width' => 3508, 'template_height' => 2480, 'sort_order' => 0]);
        $angle->photoSlots()->create(['label' => 'Üz', 'x' => 10, 'y' => 10, 'width' => 200, 'height' => 200,
            'rotation' => 0, 'shape' => 'rectangle', 'cutout' => false, 'sort_order' => 0]);

        $this->edit($box)->fillForm(['face_cutout' => true])->call('save')->assertHasNoFormErrors();

        $this->assertTrue((bool) $angle->photoSlots()->value('cutout'), 'the angle’s window was marked too');
        // And a design marked only on an angle still reads as a face design.
        $box->photoSlots()->update(['cutout' => false]);
        $this->edit($box)->assertFormSet(['face_cutout' => true]);
    }

    public function test_the_touch_up_button_stays_hidden_until_there_is_something_to_fix(): void
    {
        // The button carries .btn, whose display would beat the browser's own
        // [hidden] rule, so the sheet needs this one line of its own.
        $this->assertStringContainsString('.fix-bg[hidden]{ display:none; }', file_get_contents(public_path('css/site.css')));
    }

    public function test_a_box_without_photo_windows_saves_without_complaint(): void
    {
        $this->panel();
        $poster = Product::create(['name' => 'Poster', 'slug' => 'poster', 'is_active' => true, 'price' => 9.90]);

        $this->edit($poster)->fillForm(['face_cutout' => true])->call('save')->assertHasNoFormErrors();

        $this->assertSame(0, $poster->photoSlots()->count());
        $this->edit($poster)->assertFormSet(['face_cutout' => false]);
    }
}
