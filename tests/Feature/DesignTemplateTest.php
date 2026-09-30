<?php

namespace Tests\Feature;

use App\Models\DesignTemplate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A finished design kept aside, and laid on the next box whole.
 */
class DesignTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $done;

    private Product $fresh;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->done = Product::create(['name' => 'Qara Spotify', 'slug' => 'qara-spotify', 'is_active' => true]);
        $this->fresh = Product::create(['name' => 'Yeni qutu', 'slug' => 'yeni-qutu', 'is_active' => true]);
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(100, 200);
        imagefill($im, 0, 0, imagecolorallocate($im, 10, 10, 12));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('Fon.png', (string) ob_get_clean());
    }

    /** Draws a design on the finished box, the way the editor would. */
    private function draw(): string
    {
        $image = $this->actingAs($this->admin)
            ->post(route('box.asset', $this->done->slug), ['file' => $this->png()], ['Accept' => 'application/json'])
            ->json('image');

        $this->actingAs($this->admin)->postJson(route('box.save', $this->done->slug), [
            'layers' => [[
                'name' => 'Fon', 'image' => $image, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895,
                'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'locked' => true,
            ]],
            'shapes' => [[
                'kind' => 'rect', 'x' => 0, 'y' => 1200, 'width' => 969, 'height' => 695, 'rotation' => 0,
                'fill' => '#111111', 'stroke_color' => null, 'stroke_width' => 0, 'radius' => 0,
                'opacity' => 100, 'placement' => 'above',
            ]],
            'photos' => [[
                'label' => 'Şəkil', 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1060,
                'rotation' => 0, 'shape' => 'rectangle',
            ]],
            'texts' => [[
                'label' => 'Mahnı', 'default_value' => 'Leaving', 'placeholder' => '', 'x' => 44, 'y' => 1350,
                'max_width' => 600, 'font_size' => 50, 'color' => '#1DB954', 'align' => 'left', 'rotation' => 0,
                'font_family' => 'Inter', 'font_file' => null, 'font_weight' => 400,
                'stroke_color' => null, 'stroke_width' => 0, 'shadow_color' => null, 'shadow_blur' => 0,
                'shadow_x' => 0, 'shadow_y' => 0, 'max_lines' => 1, 'max_length' => 60, 'link_key' => null,
            ]],
            'box_color' => '#181413',
        ])->assertOk();

        return $image;
    }

    public function test_a_finished_design_goes_on_the_shelf(): void
    {
        $this->draw();

        $this->actingAs($this->admin)
            ->postJson(route('box.template.keep', $this->done->slug), ['name' => 'Spotify əsas'])
            ->assertOk()
            ->assertJson(['name' => 'Spotify əsas', 'from' => 'Qara Spotify']);

        $template = DesignTemplate::firstOrFail();
        $this->assertCount(1, $template->payload['layers']);
        $this->assertCount(1, $template->payload['texts']);
        $this->assertSame('#181413', $template->payload['box_color']);

        // And the shelf is what the editor's button shows.
        $shelf = $this->actingAs($this->admin)->getJson(route('box.templates', $this->fresh->slug))->assertOk()->json();
        $this->assertSame('Spotify əsas', $shelf['templates'][0]['name']);
        $this->assertSame(['qara-spotify'], array_column($shelf['boxes'], 'slug'));
    }

    public function test_an_empty_box_has_nothing_to_keep(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('box.template.keep', $this->fresh->slug), ['name' => 'Boş'])
            ->assertStatus(422);
    }

    public function test_a_template_is_laid_on_the_new_box_with_its_pictures(): void
    {
        $source = $this->draw();
        $this->actingAs($this->admin)->postJson(route('box.template.keep', $this->done->slug), ['name' => 'Əsas']);
        $id = DesignTemplate::firstOrFail()->id;

        $design = $this->actingAs($this->admin)
            ->postJson(route('box.template.use', $this->fresh->slug), ['template' => $id])
            ->assertOk()->json();

        $this->assertCount(1, $design['layers']);
        $this->assertCount(1, $design['shapes']);
        $this->assertCount(1, $design['photos']);
        $this->assertCount(1, $design['texts']);
        $this->assertTrue($design['layers'][0]['locked'], 'what was pinned down stays pinned down');

        // The picture is this box's own copy, and the old box keeps its file.
        $copied = $design['layers'][0]['image'];
        $this->assertStringStartsWith('boxes/' . $this->fresh->id . '/', $copied);
        $this->assertNotSame($source, $copied);
        Storage::disk('public')->assertExists($copied);
        Storage::disk('public')->assertExists($source);

        // Nothing is saved by itself: the owner looks at it and presses Saxla.
        $this->assertSame(0, $this->fresh->layers()->count());

        $this->actingAs($this->admin)->postJson(route('box.save', $this->fresh->slug), [
            'layers' => $design['layers'], 'shapes' => $design['shapes'],
            'photos' => $design['photos'], 'texts' => $design['texts'],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertSame($copied, $this->fresh->fresh()->layers()->first()->image);
    }

    public function test_another_boxs_design_can_be_borrowed_without_keeping_it(): void
    {
        $this->draw();

        $design = $this->actingAs($this->admin)
            ->postJson(route('box.template.use', $this->fresh->slug), ['from' => 'qara-spotify'])
            ->assertOk()->json();

        $this->assertCount(1, $design['layers']);
        $this->assertStringStartsWith('boxes/' . $this->fresh->id . '/', $design['layers'][0]['image']);
        $this->assertSame(0, DesignTemplate::count(), 'borrowing keeps nothing on the shelf');
    }

    public function test_deleting_a_template_leaves_every_box_alone(): void
    {
        $this->draw();
        $this->actingAs($this->admin)->postJson(route('box.template.keep', $this->done->slug), ['name' => 'Əsas']);
        $template = DesignTemplate::firstOrFail();

        $this->actingAs($this->admin)
            ->deleteJson(route('box.template.forget', [$this->done->slug, $template->id]))
            ->assertOk();

        $this->assertSame(0, DesignTemplate::count());
        $this->assertSame(1, $this->done->fresh()->layers()->count());
    }

    public function test_the_shelf_is_the_admins_alone(): void
    {
        $customer = User::factory()->create(['is_admin' => false]);

        $this->actingAs($customer)->getJson(route('box.templates', $this->fresh->slug))->assertForbidden();
        $this->actingAs($customer)->postJson(route('box.template.keep', $this->fresh->slug), ['name' => 'X'])->assertForbidden();
        $this->actingAs($customer)->postJson(route('box.template.use', $this->fresh->slug), ['from' => 'qara-spotify'])->assertForbidden();
    }
}
