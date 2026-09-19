<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\Scene;
use App\Models\User;
use App\Support\ImageColor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CoverAndBoxColourTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /** Like the Spotify box: a colourful photo on top, a solid colour below. */
    private function visual(int $r = 17, int $g = 17, int $b = 17): UploadedFile
    {
        $im = imagecreatetruecolor(97, 190);
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $b));
        mt_srand(7);
        for ($y = 0; $y < 114; $y++) {
            for ($x = 0; $x < 97; $x++) {
                imagesetpixel($im, $x, $y, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }
        ob_start();
        imagejpeg($im, null, 95);

        return UploadedFile::fake()->createWithContent('visual.jpg', ob_get_clean());
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Qara Spotify', 'slug' => 'qara-spotify', 'is_active' => true,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    private function scene(): Scene
    {
        return Scene::create(['name' => 'Sarı fon', 'elements' => [
            ['id' => 'd1', 'type' => 'design', 'corners' => [[0, 0], [10, 0], [10, 20], [0, 20]], 'opacity' => 100],
        ]]);
    }

    public function test_the_box_colour_is_read_off_the_designs_edges_not_its_photo(): void
    {
        $file = $this->visual(20, 90, 40);
        $colour = ImageColor::edgeColor($file->getRealPath());

        [$r, $g, $b] = sscanf($colour, '#%02x%02x%02x');
        $this->assertEqualsWithDelta(20, $r, 10);
        $this->assertEqualsWithDelta(90, $g, 10);
        $this->assertEqualsWithDelta(40, $b, 10);
    }

    public function test_the_box_follows_the_design_unless_the_owner_picks_a_colour(): void
    {
        $box = $this->box();
        $this->actingAs($this->admin)
            ->post(route('box.visual', $box->slug), ['file' => $this->visual()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('box_color_auto', fn ($c) => is_string($c) && hexdec(substr($c, 1, 2)) < 40);

        $auto = $box->fresh()->box_color_auto;
        $this->assertSame($auto, $this->get(route('products.customize', $box->slug))->viewData('viewData')[0]['boxColor']);

        // A colour picked by hand (or with the pipette) wins.
        $box->forceFill(['box_color' => '#1f6f43'])->save();
        $this->assertSame('#1f6f43', $this->get(route('products.customize', $box->slug))->viewData('viewData')[0]['boxColor']);
    }

    public function test_a_cover_is_uploaded_and_becomes_the_catalogue_image(): void
    {
        $box = $this->box();
        $box->forceFill(['preview_image' => 'boxes/' . $box->id . '/visual.jpg', 'cover_scene_id' => $this->scene()->id])->save();

        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cover.store', $box->id), ['file' => $this->visual()], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAs($this->admin)->post(route('cover.store', $box->id), ['file' => $this->visual()], ['Accept' => 'application/json'])->assertOk();
        $cover = $box->fresh()->cover_image;
        $this->assertStringStartsWith('boxes/' . $box->id . '/cover-', $cover);
        Storage::disk('public')->assertExists($cover);
        $this->assertSame($cover, $box->fresh()->catalogImage());

        // Uploading again replaces the old file.
        $this->actingAs($this->admin)->post(route('cover.store', $box->id), ['file' => $this->visual()], ['Accept' => 'application/json'])->assertOk();
        Storage::disk('public')->assertMissing($cover);
    }

    public function test_the_cover_page_draws_only_what_it_can_and_goes_back_home(): void
    {
        $scene = $this->scene();
        $ready = $this->box();
        $ready->forceFill(['preview_image' => 'boxes/' . $ready->id . '/visual.jpg', 'cover_scene_id' => $scene->id])->save();
        $noVisual = Product::create(['name' => 'Boş', 'slug' => 'bos', 'cover_scene_id' => $scene->id]);

        $page = $this->actingAs($this->admin)
            ->get(route('cover.page', ['ids' => $ready->id . ',' . $noVisual->id, 'back' => '//evil.example/x']))
            ->assertOk()
            ->assertSee('js/cover.js');

        $this->assertCount(1, $page->viewData('jobs'));
        $this->assertSame($ready->id, $page->viewData('jobs')[0]['id']);
        $this->assertSame('/admin/products', $page->viewData('back'));
    }

    public function test_deleting_a_scene_takes_its_covers_with_it(): void
    {
        $scene = $this->scene();
        $box = $this->box();
        Storage::disk('public')->put('boxes/' . $box->id . '/cover-x.webp', 'x');
        $box->forceFill(['cover_scene_id' => $scene->id, 'cover_image' => 'boxes/' . $box->id . '/cover-x.webp'])->save();

        $scene->delete();

        $this->assertNull($box->fresh()->cover_scene_id);
        $this->assertNull($box->fresh()->cover_image);
        Storage::disk('public')->assertMissing('boxes/' . $box->id . '/cover-x.webp');
    }

    public function test_picking_a_cover_scene_in_the_panel_goes_on_to_draw_it(): void
    {
        $scene = $this->scene();
        $box = $this->box();
        $box->forceFill(['preview_image' => 'boxes/' . $box->id . '/visual.jpg'])->save();

        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditProduct::class, ['record' => $box->getRouteKey()])
            ->fillForm(['cover_scene_id' => $scene->id])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertRedirectContains('/qapaq-yarat?ids=' . $box->id);

        $this->assertSame($scene->id, (int) $box->fresh()->cover_scene_id);
    }
}
