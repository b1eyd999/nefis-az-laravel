<?php

namespace Tests\Feature;

use App\Models\DesignLayer;
use App\Models\Font;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BoxEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $box;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->box = Product::create(['name' => 'Qara Spotify', 'slug' => 'qara-spotify', 'is_active' => true]);
    }

    private function transparentPng(int $w, int $h): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledrectangle($im, 0, (int) ($h / 2), $w - 1, $h - 1, imagecolorallocatealpha($im, 24, 20, 19, 0));
        ob_start();
        imagepng($im);
        $png = ob_get_clean();

        return UploadedFile::fake()->createWithContent('BG.png', $png);
    }

    private function design(string $image): array
    {
        return [
            'layers' => [
                ['name' => 'BG', 'image' => $image, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895,
                    'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'locked' => true],
            ],
            'photos' => [
                ['label' => 'Şəkil', 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1060, 'rotation' => 0, 'shape' => 'rectangle'],
            ],
            'texts' => [
                ['label' => 'Mahnı', 'default_value' => 'Leaving', 'placeholder' => '', 'x' => 44, 'y' => 1350,
                    'max_width' => 600, 'font_size' => 50, 'color' => '#1DB954', 'align' => 'left', 'rotation' => 0,
                    'font_family' => 'SF Semibold', 'font_file' => 'fonts/sanfrancisco-semibold.woff2', 'font_weight' => 400,
                    'stroke_color' => null, 'stroke_width' => 0, 'shadow_color' => null, 'shadow_blur' => 0,
                    'shadow_x' => 0, 'shadow_y' => 0, 'max_lines' => 1, 'max_length' => 60, 'link_key' => null],
            ],
        ];
    }

    public function test_only_admins_reach_the_editor(): void
    {
        $this->get(route('box.edit', $this->box->slug))->assertRedirect(route('login'));

        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($customer)->get(route('box.edit', $this->box->slug))->assertForbidden();
        $this->actingAs($customer)->postJson(route('box.save', $this->box->slug), $this->design('boxes/1/x.webp'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('box.edit', $this->box->slug))
            ->assertOk()
            ->assertSee('Qutu redaktoru')
            ->assertSee('js/box-render.js');
    }

    public function test_an_uploaded_layer_is_stored_small_and_keeps_its_size(): void
    {
        $res = $this->actingAs($this->admin)
            ->post(route('box.asset', $this->box->slug), ['file' => $this->transparentPng(969, 1895)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['name' => 'BG', 'width' => 969, 'height' => 1895]);

        $path = $res->json('image');
        $this->assertStringStartsWith('boxes/' . $this->box->id . '/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        // WebP keeps the transparency the fade relies on.
        $im = imagecreatefromstring(Storage::disk('public')->get($path));
        $this->assertSame(127, (imagecolorat($im, 10, 10) >> 24) & 0x7F, 'top should stay transparent');
        $this->assertSame(0, (imagecolorat($im, 10, 1800) >> 24) & 0x7F, 'bottom should stay opaque');
    }

    public function test_saving_builds_the_box_the_customer_sees(): void
    {
        $image = $this->actingAs($this->admin)
            ->post(route('box.asset', $this->box->slug), ['file' => $this->transparentPng(969, 1895)], ['Accept' => 'application/json'])
            ->json('image');

        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $this->design($image))
            ->assertOk()->assertJson(['ok' => true]);

        $box = $this->box->fresh();
        $this->assertSame(969, (int) $box->template_width);
        $this->assertSame(1895, (int) $box->template_height);
        $this->assertTrue($box->isCustomizable());
        $this->assertSame(DesignLayer::ABOVE, $box->layers()->first()->placement);
        $this->assertTrue($box->layers()->first()->locked);
        $this->assertSame('Leaving', $box->textSlots()->first()->default_value);
        // An explicit 0, not null: null would bring back the legacy outline.
        $this->assertNotNull($box->textSlots()->first()->stroke_width);
        $this->assertEquals(0, $box->textSlots()->first()->stroke_width);

        // Every scene shows the same flat design, the layer included.
        $page = $this->get(route('products.customize', $box->slug))->assertOk();
        $views = $page->viewData('viewData');
        $this->assertCount(count(config('boxes.scenes')), $views);
        $this->assertSame(1060, $views[0]['areas'][0]['h']);
        $this->assertCount(1, $views[0]['layers']['above']);
        $this->assertSame('Leaving', $box->textSlots()->first()->default_value);
        $page->assertSee('Mahnı');
    }

    public function test_a_layer_from_another_box_is_refused(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('box.save', $this->box->slug), $this->design('boxes/999/stolen.webp'))
            ->assertStatus(422);

        $this->assertSame(0, $this->box->layers()->count());
    }

    public function test_the_visual_becomes_the_catalogue_image(): void
    {
        $visual = UploadedFile::fake()->image('qara spotify.jpg', 969, 1895);

        $res = $this->actingAs($this->admin)
            ->post(route('box.visual', $this->box->slug), ['file' => $visual], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['warning' => null]);

        $this->assertNotNull($res->json('url'));
        Storage::disk('public')->assertExists($this->box->fresh()->preview_image);
    }

    public function test_a_visual_of_the_wrong_size_is_flagged(): void
    {
        $this->actingAs($this->admin)
            ->post(route('box.visual', $this->box->slug), ['file' => UploadedFile::fake()->image('v.jpg', 1000, 1000)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('warning', fn ($w) => str_contains($w, '1000×1000'));
    }

    public function test_fonts_are_checked_and_get_a_family_of_their_own(): void
    {
        $fake = UploadedFile::fake()->createWithContent('Circular.ttf', 'not a font at all');
        $this->actingAs($this->admin)
            ->post(route('box.font', $this->box->slug), ['file' => $fake, 'name' => 'Circular'], ['Accept' => 'application/json'])
            ->assertStatus(422);

        $ttf = UploadedFile::fake()->createWithContent('Circular.ttf', "\x00\x01\x00\x00" . str_repeat("\0", 64));
        $this->actingAs($this->admin)
            ->post(route('box.font', $this->box->slug), ['file' => $ttf, 'name' => 'Circular'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['name' => 'Circular', 'family' => 'NF Circular', 'weight' => 400]);

        $font = Font::where('name', 'Circular')->firstOrFail();
        Storage::disk('public')->assertExists($font->file);
    }

    public function test_deleting_a_box_removes_its_uploads(): void
    {
        $image = $this->actingAs($this->admin)
            ->post(route('box.asset', $this->box->slug), ['file' => $this->transparentPng(100, 100)], ['Accept' => 'application/json'])
            ->json('image');
        Storage::disk('public')->assertExists($image);

        $this->box->delete();

        Storage::disk('public')->assertMissing($image);
    }
}
