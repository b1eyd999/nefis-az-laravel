<?php

namespace Tests\Feature;

use App\Filament\Resources\BoxSizeResource\Pages\CreateBoxSize;
use App\Filament\Resources\BoxSizeResource\Pages\EditBoxSize;
use App\Models\BoxSize;
use App\Models\Setting;
use App\Models\User;
use App\Support\CorporatePage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Where the printed face sits inside the owner's render — four corners
 * dragged onto it, because his boxes are rendered turned and tilted and on
 * those the face is not a rectangle at all.
 *
 * The dragging is in the browser and cannot be driven from here. What can:
 * that the frame is on the screen, that the corners reach the page, and —
 * the part that matters — that four points which do not describe a sane
 * face are never handed to the warper. It would not fail on a bow tie; it
 * would paint a company's logo folded over itself.
 */
class TheFaceIsMarkedByDraggingTest extends TestCase
{
    use RefreshDatabase;

    /** A box standing turned: its face is a leaning quadrilateral. */
    private const TURNED = [[0.18, 0.09], [0.74, 0.03], [0.78, 0.88], [0.21, 0.95]];

    private const WHOLE = [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::CORPORATE_ENABLED, '1');
        BoxSize::query()->delete();
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

    // ------------------------------------------------------------ the screen

    public function test_the_frame_is_on_the_screen_where_the_size_is_edited(): void
    {
        $page = $this->get(EditBoxSize::getUrl(['record' => $this->made()->id]))->assertOk();

        $page->assertSee('bfp-frame', false);
        $page->assertSee('Çap olunan üz');
        // It draws with the shop's own warper, not one of its own.
        $page->assertSee('js/scene-render.js', false);

        $this->get(CreateBoxSize::getUrl())->assertOk()->assertSee('bfp-frame', false);
    }

    public function test_with_no_render_it_asks_for_one_first(): void
    {
        $this->get(EditBoxSize::getUrl(['record' => $this->made()->id]))->assertOk()
            ->assertSee('Əvvəlcə yuxarıda qutunun şəklini yükləyin');
    }

    /** The four points the frame writes are what the box is saved with. */
    public function test_the_corners_the_frame_writes_are_saved(): void
    {
        $size = $this->made();

        Livewire::test(EditBoxSize::class, ['record' => $size->id])
            ->fillForm(['face_quad' => self::TURNED])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(self::TURNED, $size->refresh()->faceCorners());
    }

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

    // ------------------------------------------------------------- the page

    public function test_the_corners_reach_the_drawing(): void
    {
        $this->made(['face_quad' => self::TURNED]);

        $this->assertSame(self::TURNED, CorporatePage::sizes()[0]['quad']);

        // And into the script on the page itself.
        $this->get('/sirketler-ucun')->assertOk()->assertSee('"quad":[[0.18,0.09]', false);
    }

    /**
     * The page before any size exists must look like a size to the script.
     *
     * It is built by hand rather than from a row, so a field added to one
     * and not the other is a «500 Server Error» on a page that was working
     * — which is exactly what happened the first time `quad` was added.
     */
    public function test_the_built_in_size_is_shaped_like_a_real_one(): void
    {
        $plain = CorporatePage::sizes()[0];
        $this->made();
        $real = CorporatePage::sizes()[0];

        $this->assertSame(array_keys($real), array_keys($plain));
        $this->get('/sirketler-ucun')->assertOk();
    }

    // --------------------------------------------------- what is refused

    /** With no corners placed, the size's rectangle answers. */
    public function test_a_size_whose_corners_were_never_placed_uses_its_rectangle(): void
    {
        $size = $this->made(['face_left' => 20, 'face_top' => 10, 'face_right' => 80, 'face_bottom' => 90]);

        $this->assertNull($size->face_quad);
        $this->assertSame([[0.2, 0.1], [0.8, 0.1], [0.8, 0.9], [0.2, 0.9]], $size->faceCorners());
    }

    /**
     * A bow tie is not a face.
     *
     * Drag one corner past the two beside it and the four points still look
     * like four points. The warper would paint the logo folded over itself
     * across the box rather than refuse, so it never gets there.
     */
    public function test_four_points_that_cross_are_refused(): void
    {
        $crossed = $this->made(['face_quad' => [[0.1, 0.1], [0.9, 0.1], [0.1, 0.9], [0.9, 0.9]]]);

        $this->assertSame(self::WHOLE, $crossed->faceCorners());
    }

    public function test_four_points_on_top_of_each_other_are_refused(): void
    {
        $tiny = $this->made(['face_quad' => [[0.5, 0.5], [0.505, 0.5], [0.505, 0.505], [0.5, 0.505]]]);

        $this->assertSame(self::WHOLE, $tiny->faceCorners());
    }

    /**
     * @dataProvider nonsense
     */
    public function test_nonsense_in_the_column_falls_back_rather_than_failing(mixed $saved): void
    {
        $size = $this->made(['face_quad' => $saved]);

        $this->assertSame(self::WHOLE, $size->faceCorners());
        // And the page it feeds still opens.
        $this->get('/sirketler-ucun')->assertOk();
    }

    public static function nonsense(): array
    {
        return [
            'three points' => [[[0.1, 0.1], [0.9, 0.1], [0.9, 0.9]]],
            'five points' => [[[0, 0], [1, 0], [1, 1], [0, 1], [0.5, 0.5]]],
            'a point with one number' => [[[0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]]],
            'words instead of numbers' => [[['a', 'b'], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]]],
            'not a list at all' => ['sol üst künc'],
        ];
    }

    /** A point off the edge of the picture is pulled back onto it. */
    public function test_a_corner_outside_the_picture_is_brought_back(): void
    {
        $size = $this->made(['face_quad' => [[-0.4, -0.2], [1.6, 0.0], [1.3, 1.9], [0.0, 1.2]]]);

        foreach ($size->faceCorners() as [$x, $y]) {
            $this->assertGreaterThanOrEqual(0.0, $x);
            $this->assertLessThanOrEqual(1.0, $x);
            $this->assertGreaterThanOrEqual(0.0, $y);
            $this->assertLessThanOrEqual(1.0, $y);
        }
    }
}
