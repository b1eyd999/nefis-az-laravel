<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Scene;
use App\Models\SceneAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SceneEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function png(int $w, int $h, bool $transparent = true): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, $transparent ? 127 : 0));
        imagefilledrectangle($im, (int) ($w / 4), (int) ($h / 4), (int) ($w * 3 / 4), (int) ($h * 3 / 4), imagecolorallocatealpha($im, 220, 220, 220, 0));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('box render.png', ob_get_clean());
    }

    private function upload(string $kind, ?UploadedFile $file = null): array
    {
        return $this->actingAs($this->admin)
            ->post(route('scene.asset'), ['file' => $file ?? $this->png(160, 200), 'kind' => $kind], ['Accept' => 'application/json'])
            ->assertOk()
            ->json();
    }

    /** A scene with a background, a render, and the design pinned on it. */
    private function buildScene(Scene $scene): array
    {
        $bg = $this->upload(SceneAsset::BACKGROUND, $this->png(160, 200, false));
        $box = $this->upload(SceneAsset::OBJECT);

        $payload = [
            'name' => 'Sarı fon', 'is_active' => true,
            'background' => $bg['image'], 'background_color' => null,
            'width' => 160, 'height' => 200,
            'elements' => [
                ['id' => 'r1', 'type' => 'image', 'name' => 'Qutu', 'image' => $box['image'],
                    'x' => 0, 'y' => 0, 'width' => 160, 'height' => 200, 'rotation' => 0, 'opacity' => 100,
                    'blend' => 'source-over', 'flip_x' => false, 'flip_y' => false, 'locked' => true, 'hidden' => false,
                    'url' => 'ignored'],
                ['id' => 'd1', 'type' => 'design', 'name' => 'Dizayn',
                    'corners' => [[40, 50], [118, 52], [120, 150], [41, 148.5]],
                    'opacity' => 100, 'blend' => 'source-over', 'shade' => 100, 'shade_from' => 'r1',
                    'locked' => false, 'hidden' => false],
            ],
            'preview' => 'data:image/jpeg;base64,' . base64_encode("\xFF\xD8\xFF\xE0" . str_repeat("\0", 32)),
        ];

        $this->actingAs($this->admin)->postJson(route('scene.save', $scene), $payload)->assertOk()->assertJson(['ok' => true]);

        return [$bg, $box];
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Qara Spotify', 'slug' => 'qara-spotify', 'is_active' => true,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $box->id . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_only_admins_reach_the_scene_editor(): void
    {
        $scene = Scene::create(['name' => 'Sınaq']);

        $this->get(route('scene.edit', $scene))->assertRedirect(route('login'));

        $customer = User::factory()->create(['is_admin' => false]);
        $this->actingAs($customer)->get(route('scene.edit', $scene))->assertForbidden();
        $this->actingAs($customer)->postJson(route('scene.save', $scene), [])->assertForbidden();
        $this->actingAs($customer)->post(route('scene.asset'), ['kind' => 'object'], ['Accept' => 'application/json'])->assertForbidden();

        $this->actingAs($this->admin)->get(route('scene.edit', $scene))
            ->assertOk()
            ->assertSee('Səhnə redaktoru')
            ->assertSee('js/scene-render.js');
    }

    public function test_library_pictures_are_stored_as_webp_with_their_transparency(): void
    {
        $asset = $this->upload(SceneAsset::OBJECT);

        $this->assertSame('box render', $asset['name']);
        $this->assertSame([160, 200], [$asset['width'], $asset['height']]);
        $this->assertStringStartsWith(SceneAsset::DIRECTORY . '/', $asset['image']);
        $this->assertStringEndsWith('.webp', $asset['image']);

        $im = imagecreatefromstring(Storage::disk('public')->get($asset['image']));
        $this->assertSame(127, (imagecolorat($im, 2, 2) >> 24) & 0x7F, 'the corner should stay see-through');
        $this->assertSame(0, (imagecolorat($im, 80, 100) >> 24) & 0x7F, 'the box should stay solid');
    }

    public function test_an_oversized_picture_is_shrunk_on_the_server_too(): void
    {
        $asset = $this->upload(SceneAsset::BACKGROUND, $this->png(3080, 3850, false));

        $this->assertLessThanOrEqual(2600, max($asset['width'], $asset['height']));
        $this->assertEqualsWithDelta(3080 / 3850, $asset['width'] / $asset['height'], 0.01);
    }

    public function test_saving_keeps_the_corner_pin_and_the_customer_sees_it(): void
    {
        $scene = Scene::create(['name' => 'Sınaq']);
        [$bg, $box] = $this->buildScene($scene);

        $scene->refresh();
        $this->assertSame('Sarı fon', $scene->name);
        $this->assertSame($bg['image'], $scene->background);
        $this->assertSame([[40, 50], [118, 52], [120, 150], [41, 148.5]], $scene->elements[1]['corners']);
        $this->assertSame('r1', $scene->elements[1]['shade_from']);
        $this->assertArrayNotHasKey('url', $scene->elements[0], 'derived urls are not stored');
        Storage::disk('public')->assertExists($scene->preview_image);

        $page = $this->get(route('products.customize', $this->box()->slug))->assertOk();
        $views = $page->viewData('viewData');

        // The owner's scene, then the design on its own.
        $this->assertCount(2, $views);
        $this->assertSame('Sarı fon', $views[0]['label']);
        $this->assertSame(160, $views[0]['scene']['w']);
        $this->assertStringContainsString($bg['image'], $views[0]['scene']['bg']);
        $this->assertStringContainsString($box['image'], $views[0]['scene']['elements'][0]['url']);
        $this->assertSame('design', $views[0]['scene']['elements'][1]['type']);
        $this->assertNull($views[1]['scene']);
        $page->assertSee('js/scene-render.js');
    }

    public function test_pictures_from_outside_the_library_are_refused(): void
    {
        $scene = Scene::create(['name' => 'Sınaq']);
        $payload = ['name' => 'X', 'width' => 100, 'height' => 100, 'elements' => [
            ['id' => 'a', 'type' => 'image', 'image' => 'boxes/1/secret.webp', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'opacity' => 100],
        ]];
        $this->actingAs($this->admin)->postJson(route('scene.save', $scene), $payload)->assertStatus(422);

        $payload['elements'] = [];
        $payload['background'] = '../../.env';
        $this->actingAs($this->admin)->postJson(route('scene.save', $scene), $payload)->assertStatus(422);
    }

    public function test_hidden_and_inactive_scenes_stay_off_the_site(): void
    {
        $shown = Scene::create(['name' => 'Görünən', 'elements' => [
            ['id' => 'd', 'type' => 'design', 'corners' => [[0, 0], [10, 0], [10, 20], [0, 20]], 'opacity' => 100],
            ['id' => 'h', 'type' => 'design', 'corners' => [[0, 0], [10, 0], [10, 20], [0, 20]], 'opacity' => 100, 'hidden' => true],
        ]]);
        Scene::create(['name' => 'Söndürülmüş', 'is_active' => false]);

        $views = $this->get(route('products.customize', $this->box()->slug))->viewData('viewData');

        $this->assertSame(['Görünən', 'Düz görünüş'], array_column($views, 'label'));
        $this->assertCount(1, $views[0]['scene']['elements']);
    }

    public function test_a_product_can_be_limited_to_some_scenes(): void
    {
        $a = Scene::create(['name' => 'A', 'sort_order' => 2]);
        $b = Scene::create(['name' => 'B', 'sort_order' => 1]);
        Scene::create(['name' => 'C', 'sort_order' => 3]);
        $box = $this->box();

        $this->assertSame(['B', 'A', 'C', 'Düz görünüş'], array_column($this->get(route('products.customize', $box->slug))->viewData('viewData'), 'label'));

        $box->scenes()->sync([$a->id, $b->id]);
        $this->assertSame(['B', 'A', 'Düz görünüş'], array_column($this->get(route('products.customize', $box->slug))->viewData('viewData'), 'label'));
    }

    public function test_without_any_scene_the_stand_in_bar_scenes_fill_in(): void
    {
        $views = $this->get(route('products.customize', $this->box()->slug))->viewData('viewData');

        $this->assertCount(count(config('boxes.scenes')) + 1, $views);
        $corners = $views[0]['scene']['elements'][0]['corners'];
        $this->assertCount(4, $corners);
        // The centre of the pinned design is where the config puts the box.
        $this->assertEqualsWithDelta(config('boxes.scenes.0.cx'), array_sum(array_column($corners, 0)) / 4, 0.01);
    }

    public function test_a_render_can_be_dyed_or_follow_the_products_box_colour(): void
    {
        $scene = Scene::create(['name' => 'Sınaq']);
        $box = $this->upload(SceneAsset::OBJECT);
        $render = ['id' => 'r1', 'type' => 'image', 'name' => 'Qutu', 'image' => $box['image'], 'x' => 0, 'y' => 0,
            'width' => 160, 'height' => 200, 'opacity' => 100, 'tint' => '#1b1b1d', 'sheen' => 12, 'recolor' => true];

        $this->actingAs($this->admin)->postJson(route('scene.save', $scene), [
            'name' => 'Qara', 'width' => 160, 'height' => 200, 'elements' => [$render],
        ])->assertOk();
        $saved = $scene->fresh()->elements[0];
        $this->assertSame(['#1b1b1d', 12, true, false], [$saved['tint'], $saved['sheen'], $saved['recolor'], $saved['tint_all']]);

        $this->actingAs($this->admin)->postJson(route('scene.save', $scene), [
            'name' => 'Qara', 'width' => 160, 'height' => 200, 'elements' => [['tint' => 'black'] + $render],
        ])->assertStatus(422);

        // The product's own colour reaches the page, set from the box editor.
        $product = $this->box();
        $this->actingAs($this->admin)->postJson(route('box.save', $product->slug), [
            'layers' => [['name' => 'BG', 'image' => 'boxes/' . $product->id . '/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
                'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above']],
            'photos' => [], 'texts' => [], 'box_color' => '#1f6f43',
        ])->assertOk();
        $this->assertSame('#1f6f43', $product->fresh()->box_color);

        $views = $this->get(route('products.customize', $product->slug))->viewData('viewData');
        $this->assertSame('#1f6f43', $views[0]['boxColor']);
        $this->assertTrue($views[0]['scene']['elements'][0]['recolor']);
    }

    public function test_renders_left_on_a_fixed_colour_are_switched_to_follow_the_design(): void
    {
        $scene = Scene::create(['name' => 'test 2', 'elements' => [
            ['id' => 'r', 'type' => 'image', 'image' => 'scenes/library/x.webp', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
                'tint' => '#f2e3c6', 'sheen' => 75, 'recolor' => false, 'tint_all' => true],
            ['id' => 'd', 'type' => 'design', 'corners' => [[0, 0], [1, 0], [1, 1], [0, 1]]],
        ]]);

        $migration = require database_path('migrations/2026_09_19_000005_make_all_box_renders_follow_the_design.php');
        $migration->up();
        $migration->up(); // harmless twice

        $render = $scene->fresh()->elements[0];
        $this->assertTrue($render['recolor']);
        $this->assertSame(0, $render['sheen']);
        $this->assertFalse($render['tint_all']);
        $this->assertSame('#f2e3c6', $render['tint'], 'kept as the fallback');
        $this->assertSame([[0, 0], [1, 0], [1, 1], [0, 1]], $scene->fresh()->elements[1]['corners']);
    }

    public function test_only_the_owner_sees_the_admin_link_in_the_header(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('>Admin</a>', false);
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get(route('home'))->assertDontSee('>Admin</a>', false);
        $this->actingAs($this->admin)->get(route('home'))->assertSee('href="' . url('/admin') . '"', false)->assertSee('>Admin</a>', false);
    }

    public function test_the_admin_panel_lists_and_creates_scenes(): void
    {
        $scene = Scene::create(['name' => 'Sarı fon']);
        $product = $this->box();

        $this->actingAs($this->admin)->get('/admin/scenes')->assertOk()->assertSee('Sarı fon')->assertSee(route('scene.edit', $scene), false);
        $this->actingAs($this->admin)->get('/admin/scenes/create')->assertOk();
        $this->actingAs($this->admin)->get('/admin/scenes/' . $scene->id . '/edit')->assertOk()->assertSee('Səhnə redaktoru');
        $this->actingAs($this->admin)->get('/admin/products/' . $product->id . '/edit')->assertOk()
            ->assertSee('Qutunun rəngi (mokaplarda)')
            ->assertSee('Sarı fon');
    }

    public function test_a_library_picture_in_use_cannot_be_deleted(): void
    {
        $scene = Scene::create(['name' => 'Sınaq']);
        [$bg] = $this->buildScene($scene);
        $spare = $this->upload(SceneAsset::OBJECT);

        $this->actingAs($this->admin)->deleteJson(route('scene.asset.destroy', $bg['id']))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Sarı fon'));
        Storage::disk('public')->assertExists($bg['image']);

        $this->actingAs($this->admin)->deleteJson(route('scene.asset.destroy', $spare['id']))->assertOk();
        Storage::disk('public')->assertMissing($spare['image']);
        $this->assertNull(SceneAsset::find($spare['id']));
    }
}
