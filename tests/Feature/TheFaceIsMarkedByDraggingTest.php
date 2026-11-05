<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxSizeResource\Pages\CreateBoxSize;
use App\Filament\Resources\BoxSizeResource\Pages\EditBoxSize;
use App\Models\BoxSize;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Where the printed face sits inside the owner's render, marked by dragging
 * a frame over the picture instead of typing four percentages.
 *
 * The dragging itself is in the browser and cannot be driven from here. What
 * can be: that the frame is on the screen where the numbers are, that the
 * numbers it writes are what the box is saved with, and that a frame which
 * is not a rectangle never reaches the page — which is the only one of the
 * three that could quietly print a company's logo backwards.
 */
class TheFaceIsMarkedByDraggingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function made(array $extra = []): BoxSize
    {
        return BoxSize::create($extra + [
            'name' => 'Uzun test',
            'dimensions' => '8,5 × 16 × 1 sm',
            'width_mm' => 85,
            'height_mm' => 160,
        ]);
    }

    public function test_the_frame_is_on_the_screen_where_the_size_is_edited(): void
    {
        $page = $this->get(EditBoxSize::getUrl(['record' => $this->made()->id]))->assertOk();

        $page->assertSee('bfp-frame', false);
        $page->assertSee('Çap olunan üz');

        // And on a size being made for the first time.
        $this->get(CreateBoxSize::getUrl())->assertOk()->assertSee('bfp-frame', false);
    }

    /**
     * With no render there is nothing to drag a frame over, and the screen
     * says so rather than showing an empty rectangle on nothing.
     */
    public function test_with_no_render_it_asks_for_one_first(): void
    {
        $this->get(EditBoxSize::getUrl(['record' => $this->made()->id]))->assertOk()
            ->assertSee('Əvvəlcə yuxarıda qutunun şəklini yükləyin');
    }

    /** What the frame writes is what the box is saved with. */
    public function test_the_numbers_the_frame_writes_are_saved(): void
    {
        $size = $this->made();

        Livewire::test(EditBoxSize::class, ['record' => $size->id])
            ->fillForm([
                'face_left' => 18.5,
                'face_top' => 9,
                'face_right' => 81.5,
                'face_bottom' => 72.4,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $size->refresh();

        $this->assertSame(
            [18.5, 9.0, 81.5, 72.4],
            [$size->face_left, $size->face_top, $size->face_right, $size->face_bottom],
        );
        // A hundredth of a per cent apart: 72.4 / 100 is not 0.724 in binary.
        foreach ([0.185, 0.09, 0.815, 0.724] as $i => $fraction) {
            $this->assertEqualsWithDelta($fraction, $size->faceRect()[$i], 0.00001);
        }
    }

    /** The render reaches the model the picture is drawn from. */
    public function test_an_uploaded_render_is_what_the_frame_is_laid_over(): void
    {
        $size = $this->made();

        Livewire::test(EditBoxSize::class, ['record' => $size->id])
            ->fillForm(['front_image' => UploadedFile::fake()->image('uzun-on.png', 600, 1100)])
            ->call('save')
            ->assertHasNoFormErrors();

        $size->refresh();

        $this->assertStringStartsWith('box-sizes/', (string) $size->front_image);
        $this->assertStringContainsString($size->front_image, (string) $size->frontUrl());
    }

    /**
     * A frame dragged into nonsense never reaches the page.
     *
     * The browser holds the edges apart, but the four numbers are columns in
     * a table and the page reads them defensively: a right edge to the left
     * of the left one would have the logo painted mirrored across the box
     * rather than fail, so the whole picture is used instead.
     */
    public function test_a_frame_that_is_not_a_rectangle_is_ignored_by_the_page(): void
    {
        $backwards = $this->made(['face_left' => 70, 'face_right' => 30]);

        $this->assertSame([0.0, 0.0, 1.0, 1.0], $backwards->faceRect());
        $this->assertSame(['l' => 0.0, 't' => 0.0, 'r' => 1.0, 'b' => 1.0], $backwards->forPage()['face']);
    }
}
