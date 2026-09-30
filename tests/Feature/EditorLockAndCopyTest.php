<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Two working comforts in the box editor: pinning a piece of a design down so
 * a stray click cannot move it, and carrying a piece from one design to
 * another.
 */
class EditorLockAndCopyTest extends TestCase
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

    /** @param  array<string, mixed>  $extra */
    private function design(array $extra = []): array
    {
        return array_merge([
            'layers' => [],
            'shapes' => [[
                'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895, 'rotation' => 0,
                'fill' => '#0b0b0d', 'stroke_color' => null, 'stroke_width' => 0, 'radius' => 0,
                'opacity' => 100, 'placement' => 'below', 'locked' => true,
            ]],
            'photos' => [[
                'label' => 'Şəkil', 'x' => 40, 'y' => 60, 'width' => 800, 'height' => 800,
                'rotation' => 0, 'shape' => 'rectangle', 'locked' => true,
            ]],
            'texts' => [[
                'label' => 'Ad', 'default_value' => 'Nefis', 'placeholder' => '', 'x' => 480, 'y' => 1200,
                'max_width' => 600, 'font_size' => 60, 'color' => '#ffffff', 'align' => 'center', 'rotation' => 0,
                'font_family' => 'Inter', 'font_file' => null, 'font_weight' => 400,
                'stroke_color' => null, 'stroke_width' => 0, 'shadow_color' => null, 'shadow_blur' => 0,
                'shadow_x' => 0, 'shadow_y' => 0, 'max_lines' => 1, 'max_length' => 60, 'link_key' => null,
                'locked' => true,
            ]],
        ], $extra);
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(120, 90);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 30, 40));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('Ulduz.png', (string) ob_get_clean());
    }

    public function test_a_design_remembers_what_was_pinned_down(): void
    {
        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $this->design())
            ->assertOk()->assertJson(['ok' => true]);

        $box = $this->box->fresh();
        $this->assertTrue($box->shapes()->first()->locked);
        $this->assertTrue($box->photoSlots()->first()->locked);
        $this->assertTrue($box->textSlots()->first()->locked);

        // And the editor is told about it when the design is opened again.
        $design = $this->actingAs($this->admin)->get(route('box.edit', $this->box->slug))
            ->assertOk()->viewData('design');
        $this->assertTrue($design['shapes'][0]['locked']);
        $this->assertTrue($design['photos'][0]['locked']);
        $this->assertTrue($design['texts'][0]['locked']);
    }

    public function test_an_older_editor_tab_does_not_unlock_the_design(): void
    {
        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $this->design())->assertOk();

        // A tab opened before the lock existed says nothing about it.
        $blind = $this->design();
        foreach (['shapes', 'photos', 'texts'] as $kind) {
            unset($blind[$kind][0]['locked']);
        }
        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $blind)->assertOk();

        $box = $this->box->fresh();
        $this->assertTrue($box->shapes()->first()->locked, 'the lock should survive a save that never mentions it');
        $this->assertTrue($box->photoSlots()->first()->locked);
        $this->assertTrue($box->textSlots()->first()->locked);
    }

    public function test_a_picture_pasted_from_another_design_comes_with_its_file(): void
    {
        $other = Product::create(['name' => 'Ağ qutu', 'slug' => 'ag-qutu', 'is_active' => true]);
        $source = $this->actingAs($this->admin)
            ->post(route('box.asset', $other->slug), ['file' => $this->png()], ['Accept' => 'application/json'])
            ->assertOk()->json('image');

        $copy = $this->actingAs($this->admin)
            ->postJson(route('box.copy', $this->box->slug), ['image' => $source])
            ->assertOk()->json();

        $this->assertStringStartsWith('boxes/' . $this->box->id . '/', $copy['image']);
        $this->assertNotSame($source, $copy['image']);
        Storage::disk('public')->assertExists($copy['image']);
        // The design it came from keeps its own picture.
        Storage::disk('public')->assertExists($source);

        // And the box will accept it as one of its layers.
        $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $this->design([
            'layers' => [[
                'name' => 'Ulduz', 'image' => $copy['image'], 'x' => 0, 'y' => 0, 'width' => 120, 'height' => 90,
                'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'locked' => false,
            ]],
        ]))->assertOk()->assertJson(['ok' => true]);

        $this->assertSame($copy['image'], $this->box->fresh()->layers()->first()->image);
    }

    public function test_pasting_the_same_boxs_own_picture_makes_no_second_file(): void
    {
        $mine = $this->actingAs($this->admin)
            ->post(route('box.asset', $this->box->slug), ['file' => $this->png()], ['Accept' => 'application/json'])
            ->json('image');

        $this->actingAs($this->admin)->postJson(route('box.copy', $this->box->slug), ['image' => $mine])
            ->assertOk()->assertJson(['image' => $mine]);
    }

    public function test_only_a_box_picture_can_be_copied_and_only_by_an_admin(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('box.copy', $this->box->slug), ['image' => '../../.env'])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson(route('box.copy', $this->box->slug), ['image' => 'fonts/custom/x.woff2'])
            ->assertStatus(422);

        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($customer)
            ->postJson(route('box.copy', $this->box->slug), ['image' => 'boxes/1/x.webp'])
            ->assertForbidden();
    }
}
