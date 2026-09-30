<?php

namespace Tests\Feature;

use App\Filament\Resources\LibraryAssetResource;
use App\Models\LibraryAsset;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The shelf of pictures the box editor reaches into: frames, patterns and
 * stickers uploaded once and put on any design.
 */
class LibraryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $box;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->box = Product::create(['name' => 'Qara qutu', 'slug' => 'qara-qutu', 'is_active' => true]);
    }

    private function png(int $w, int $h): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle($im, 0, (int) ($h / 2), $w - 1, $h - 1, imagecolorallocatealpha($im, 212, 175, 55, 0));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('Qızıl çərçivə.png', (string) ob_get_clean());
    }

    private function shelve(string $category = 'frame', bool $active = true): LibraryAsset
    {
        return LibraryAsset::create([
            'name' => 'Qızıl çərçivə',
            'category' => $category,
            'image' => LibraryAssetResource::keep($this->png(600, 400)),
            'is_active' => $active,
        ]);
    }

    public function test_an_upload_lands_as_a_small_file_and_measures_itself(): void
    {
        $asset = $this->shelve();

        $this->assertStringStartsWith(LibraryAsset::DIRECTORY . '/', $asset->image);
        $this->assertStringEndsWith('.webp', $asset->image);
        Storage::disk('public')->assertExists($asset->image);

        // Nobody types the measurements in: they are read off the file.
        $this->assertSame(600, $asset->width);
        $this->assertSame(400, $asset->height);

        // And the transparency a frame is made of survives the re-encoding.
        $im = imagecreatefromstring((string) Storage::disk('public')->get($asset->image));
        $this->assertSame(127, (imagecolorat($im, 10, 10) >> 24) & 0x7F);
    }

    public function test_the_editor_is_offered_what_is_on_the_shelf(): void
    {
        $frame = $this->shelve('frame');
        $hidden = $this->shelve('sticker', active: false);

        $page = $this->actingAs($this->admin)->get(route('box.edit', $this->box->slug))->assertOk()->assertSee('Kitabxana');
        $offered = $page->viewData('library')->all();

        $this->assertCount(1, $offered);
        $this->assertSame($frame->id, $offered[0]['id']);
        $this->assertSame(600, $offered[0]['width']);
        $this->assertNotContains($hidden->id, array_column($offered, 'id'));
    }

    public function test_putting_one_on_a_box_copies_it_into_that_box(): void
    {
        $asset = $this->shelve();

        $entry = $this->actingAs($this->admin)
            ->postJson(route('box.library', [$this->box->slug, $asset->id]))
            ->assertOk()
            ->assertJson(['name' => 'Qızıl çərçivə', 'width' => 600, 'height' => 400])
            ->json();

        // Its own copy in its own folder — which is also the only kind of path
        // the editor's save will accept.
        $this->assertStringStartsWith('boxes/' . $this->box->id . '/', $entry['image']);
        $this->assertNotSame($asset->image, $entry['image']);
        Storage::disk('public')->assertExists($entry['image']);

        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), [
            'layers' => [[
                'name' => $entry['name'], 'image' => $entry['image'], 'x' => 0, 'y' => 0,
                'width' => 600, 'height' => 400, 'rotation' => 0, 'opacity' => 100,
                'placement' => 'above', 'locked' => false,
            ]],
            'photos' => [], 'texts' => [],
        ])->assertOk()->assertJson(['ok' => true]);

        // Clearing the shelf must not empty a design that is already on sale.
        $asset->delete();
        Storage::disk('public')->assertMissing($asset->image);
        Storage::disk('public')->assertExists($entry['image']);
        $this->assertSame($entry['image'], $this->box->fresh()->layers()->first()->image);
    }

    public function test_the_shelf_is_the_admins_alone(): void
    {
        $asset = $this->shelve();
        $customer = User::factory()->create(['is_admin' => false]);

        $this->postJson(route('box.library', [$this->box->slug, $asset->id]))->assertUnauthorized();
        $this->actingAs($customer)->postJson(route('box.library', [$this->box->slug, $asset->id]))->assertForbidden();
    }

    public function test_the_panel_has_a_page_of_its_own_for_the_shelf(): void
    {
        $this->shelve();

        $this->actingAs($this->admin)->get('/admin/library-assets')
            ->assertOk()
            ->assertSee('Kitabxana')
            ->assertSee('Qızıl çərçivə');

        // A manager keeps the orders; the artwork is the owner's.
        $manager = User::factory()->create(['role' => User::MANAGER]);
        $this->actingAs($manager)->get('/admin/library-assets')->assertForbidden();
    }

    public function test_a_picture_taken_off_the_shelf_is_no_longer_offered(): void
    {
        $asset = $this->shelve('pattern', active: false);

        $this->actingAs($this->admin)
            ->postJson(route('box.library', [$this->box->slug, $asset->id]))
            ->assertNotFound();
    }
}
