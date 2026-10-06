<?php

namespace Tests\Feature;

use App\Models\DesignLayer;
use App\Models\DesignShape;
use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\TextSlot;
use App\Models\User;
use App\Support\DesignStack;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * One order for the whole design.
 *
 * A box used to be drawn in six bands around the customer's photograph, and
 * the owner could not put a drawing between two faces — which is what he
 * asked for. These are the things that could not be expressed before, and the
 * two that must not change while they become possible: a window keeps the
 * form field that fills it, and a design saved before any of this existed
 * still looks exactly as it did.
 */
class FreeStackTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Product $box;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->box = Product::create(['name' => 'Təklif', 'slug' => 'teklif', 'is_active' => true]);
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(969, 1895);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('Art.png', (string) ob_get_clean());
    }

    private function upload(): string
    {
        return $this->actingAs($this->admin)
            ->post(route('box.asset', $this->box->slug), ['file' => $this->png()], ['Accept' => 'application/json'])
            ->json('image');
    }

    private function layer(string $image, ?float $z = null): array
    {
        return ['name' => 'Art', 'image' => $image, 'x' => 0, 'y' => 0, 'width' => 969, 'height' => 1895,
            'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'locked' => false] + ($z === null ? [] : ['z' => $z]);
    }

    private function window(string $label, ?float $z = null): array
    {
        return ['label' => $label, 'x' => 10, 'y' => 10, 'width' => 300, 'height' => 300,
            'rotation' => 0, 'shape' => 'rectangle'] + ($z === null ? [] : ['z' => $z]);
    }

    private function caption(string $words, ?float $z = null): array
    {
        return ['label' => 'Mətn', 'default_value' => $words, 'placeholder' => '', 'x' => 44, 'y' => 1350,
            'max_width' => 600, 'font_size' => 50, 'color' => '#000000', 'align' => 'left', 'rotation' => 0,
            'font_family' => 'SF Regular', 'font_file' => null, 'font_weight' => 400,
            'stroke_color' => null, 'stroke_width' => 0, 'shadow_color' => null, 'shadow_blur' => 0,
            'shadow_x' => 0, 'shadow_y' => 0, 'max_lines' => 1, 'max_length' => 60,
            'link_key' => null] + ($z === null ? [] : ['z' => $z]);
    }

    private function save(array $design): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->postJson(route('box.save', $this->box->slug), $design);
    }

    /** Every z written for this box, table by table, lowest first. */
    private function zs(): array
    {
        $box = $this->box->fresh();
        $all = [];
        foreach ($box->layers as $l) {
            $all[] = ['layer', $l->z];
        }
        foreach ($box->shapes as $s) {
            $all[] = ['shape', $s->z];
        }
        foreach ($box->photoSlots as $p) {
            $all[] = ['photo', $p->z];
        }
        foreach ($box->textSlots as $t) {
            $all[] = ['text', $t->z];
        }

        return $all;
    }

    public function test_the_owner_puts_a_layer_between_two_photo_windows(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 1)],
            'photos' => [$this->window('Xanım', 0), $this->window('Bəy', 2)],
            'texts' => [],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertSame(0, (int) $box->photoSlots[0]->z);
        $this->assertSame(1, (int) $box->layers[0]->z);
        $this->assertSame(2, (int) $box->photoSlots[1]->z);

        // The form fields did not move with the drawing order.
        $this->assertSame('Xanım', $box->photoSlots[0]->label);
        $this->assertSame(0, (int) $box->photoSlots[0]->sort_order);
        $this->assertSame(1, (int) $box->photoSlots[1]->sort_order);

        $views = $this->get(route('products.customize', $box->slug))->assertOk()->viewData('viewData');
        $kinds = array_map(fn (array $e) => $e['kind'] . (isset($e['i']) ? ':' . $e['i'] : ''), $views[0]['stack']);
        $this->assertSame(['area:0', 'layer', 'area:1'], $kinds);
    }

    public function test_a_caption_goes_under_the_artwork(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 5)],
            'photos' => [$this->window('Şəkil', 0)],
            'texts' => [$this->caption('Sevgilim', 1)],
        ])->assertOk();

        $views = $this->get(route('products.customize', $this->box->slug))->assertOk()->viewData('viewData');
        $kinds = array_column($views[0]['stack'], 'kind');
        $this->assertSame(['area', 'text', 'layer'], $kinds);
    }

    public function test_the_windows_keep_their_fields_when_their_order_changes(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 0)],
            'photos' => [$this->window('Şəkil 1', 1), $this->window('Şəkil 2', 2)],
            'texts' => [],
        ])->assertOk();

        // Now the second window is drawn first — the fields must not follow.
        $this->save([
            'layers' => [$this->layer($image, 0)],
            'photos' => [$this->window('Şəkil 1', 2), $this->window('Şəkil 2', 1)],
            'texts' => [],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertSame('Şəkil 1', $box->photoSlots[0]->label);
        $this->assertSame(0, (int) $box->photoSlots[0]->sort_order);
        $this->assertSame(1, (int) $box->photoSlots[1]->sort_order);
        $this->assertGreaterThan((int) $box->photoSlots[1]->z, (int) $box->photoSlots[0]->z);

        /* The form still asks for them in their own order, and the stack
           now draws the second one first. */
        $views = $this->get(route('products.customize', $box->slug))->assertOk()->viewData('viewData');
        $this->assertSame(['area:1', 'area:0'], array_map(
            fn (array $e) => $e['kind'] . ':' . $e['i'],
            array_values(array_filter($views[0]['stack'], fn (array $e) => $e['kind'] === 'area'))
        ));
        $this->get(route('products.customize', $box->slug))->assertSee('Şəkil 1')->assertSee('Şəkil 2');
    }

    public function test_a_save_from_an_older_editor_keeps_the_old_look(): void
    {
        $image = $this->upload();
        // No z anywhere: a tab opened before the stack existed.
        $this->save([
            'layers' => [$this->layer($image)],
            'photos' => [$this->window('Şəkil')],
            'texts' => [$this->caption('Leaving')],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertSame(0, (int) $box->photoSlots[0]->z);
        $this->assertSame(1, (int) $box->layers[0]->z);
        $this->assertSame(2, (int) $box->textSlots[0]->z);
        $this->assertSame(DesignLayer::ABOVE, $box->layers[0]->placement);
    }

    public function test_a_half_numbered_save_falls_back_to_the_old_bands(): void
    {
        $image = $this->upload();
        // A broken client: a place on the layer, none on the window.
        $this->save([
            'layers' => [$this->layer($image, 0)],
            'photos' => [$this->window('Şəkil')],
            'texts' => [],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertSame(0, (int) $box->photoSlots[0]->z, 'the window is drawn first, as it always was');
        $this->assertSame(1, (int) $box->layers[0]->z);
    }

    public function test_a_silent_save_does_not_scramble_the_surviving_shapes(): void
    {
        $image = $this->upload();
        $shape = fn (string $kind, float $z) => ['kind' => $kind, 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100,
            'rotation' => 0, 'fill' => '#ffffff', 'stroke_color' => null, 'stroke_width' => 0,
            'radius' => 0, 'opacity' => 100, 'placement' => 'above', 'z' => $z];

        $this->save([
            'layers' => [$this->layer($image, 3)],
            'shapes' => [$shape('rect', 1), $shape('line', 2)],
            'photos' => [$this->window('Şəkil', 0)],
            'texts' => [],
        ])->assertOk();

        $before = DesignStack::of($this->box->fresh());

        // An editor tab older than shapes: it says nothing about them, so they
        // live through the save and have to be numbered with the rest.
        $this->save([
            'layers' => [$this->layer($image, 3)],
            'photos' => [$this->window('Şəkil', 0)],
            'texts' => [],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertCount(2, $box->shapes, 'a silent save leaves the artwork alone');
        $this->assertSame($before, DesignStack::of($box));

        $written = array_column($this->zs(), 1);
        sort($written);
        $this->assertSame(range(0, count($written) - 1), array_map('intval', $written));
    }

    public function test_the_stack_is_renumbered_dense_and_unique(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 7)],
            'photos' => [$this->window('Bir', 7), $this->window('İki', 900)],
            'texts' => [$this->caption('Söz', 0.5)],
        ])->assertOk();

        $written = array_map('intval', array_column($this->zs(), 1));
        sort($written);
        $this->assertSame(range(0, 3), $written);
    }

    public function test_placement_follows_the_stack(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 0)],
            'photos' => [$this->window('Şəkil', 1)],
            'texts' => [],
        ])->assertOk();
        $this->assertSame(DesignLayer::BELOW, $this->box->fresh()->layers[0]->placement);

        $this->save([
            'layers' => [$this->layer($image, 2)],
            'photos' => [$this->window('Şəkil', 1)],
            'texts' => [],
        ])->assertOk();
        $this->assertSame(DesignLayer::ABOVE, $this->box->fresh()->layers[0]->placement);
    }

    public function test_the_old_grouping_still_draws_while_the_column_is_filling(): void
    {
        $image = $this->upload();
        $this->save([
            'layers' => [$this->layer($image, 1)],
            'photos' => [$this->window('Şəkil', 0)],
            'texts' => [],
        ])->assertOk();

        // The minutes between the files landing on the hosting and the
        // migration running, and any product the backfill has not reached.
        DB::table('design_layers')->update(['z' => null]);

        $views = $this->get(route('products.customize', $this->box->slug))->assertOk()->viewData('viewData');
        $this->assertSame([], $views[0]['stack']);
        $this->assertCount(1, $views[0]['layers']['above']);
    }

    public function test_an_existing_design_is_ranked_the_way_it_was_drawn(): void
    {
        /* Built straight through the models, the way a seeder does: placement
           and sort_order, and no stack at all. */
        $this->box->layers()->create(['name' => 'Over', 'image' => 'boxes/1/a.webp', 'x' => 0, 'y' => 0,
            'width' => 10, 'height' => 10, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 1]);
        $this->box->layers()->create(['name' => 'Under', 'image' => 'boxes/1/b.webp', 'x' => 0, 'y' => 0,
            'width' => 10, 'height' => 10, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $this->box->shapes()->create(['kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
            'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $this->box->photoSlots()->create(['label' => 'Şəkil', 'x' => 0, 'y' => 0, 'width' => 10, 'height' => 10,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0]);
        $this->box->textSlots()->create(['label' => 'Mətn', 'x' => 0, 'y' => 0, 'max_width' => 10, 'font_size' => 10,
            'color' => '#000000', 'align' => 'left', 'rotation' => 0, 'max_lines' => 1, 'max_length' => 60, 'sort_order' => 0]);

        DB::table('design_layers')->update(['z' => null]);
        DB::table('design_shapes')->update(['z' => null]);
        DB::table('photo_slots')->update(['z' => null]);
        DB::table('text_slots')->update(['z' => null]);

        $migration = require database_path('migrations/2026_10_06_000100_the_design_is_one_free_stack.php');

        $read = function () {
            return [
                'under' => (int) DesignLayer::where('name', 'Under')->value('z'),
                'shape' => (int) DesignShape::query()->value('z'),
                'photo' => (int) PhotoSlot::query()->value('z'),
                'over' => (int) DesignLayer::where('name', 'Over')->value('z'),
                'text' => (int) TextSlot::query()->value('z'),
            ];
        };

        $migration->up();
        $first = $read();
        $this->assertSame(['under' => 0, 'shape' => 1, 'photo' => 2, 'over' => 3, 'text' => 4], $first);

        /* Run twice. A migration that fell over halfway on the hosting is
           picked up again by hand, and there is no shell to repair anything
           with. (This suite runs on SQLite, so it proves the backfill is
           idempotent, not the DDL — the Schema::hasColumn guards cover that.) */
        $migration->up();
        $this->assertSame($first, $read());
    }

    public function test_a_save_works_in_the_minute_before_the_column_exists(): void
    {
        /* The hosting copies the files into place and only then runs the
           migrations. A save in that minute must not fail — the editor sends
           the placement it worked out, so the box keeps its order. */
        $image = $this->upload();
        \Illuminate\Support\Facades\Schema::table('design_layers', fn ($t) => $t->dropColumn('z'));
        \Illuminate\Support\Facades\Schema::table('photo_slots', fn ($t) => $t->dropColumn('z'));
        \Illuminate\Support\Facades\Schema::table('text_slots', fn ($t) => $t->dropColumn('z'));
        \Illuminate\Support\Facades\Schema::table('design_shapes', fn ($t) => $t->dropColumn('z'));

        $under = $this->layer($image, 0);
        $under['placement'] = 'below';
        $this->save([
            'layers' => [$under],
            'photos' => [$this->window('Şəkil', 1)],
            'texts' => [],
        ])->assertOk();

        $box = $this->box->fresh();
        $this->assertSame(DesignLayer::BELOW, $box->layers[0]->placement);

        $views = $this->get(route('products.customize', $box->slug))->assertOk()->viewData('viewData');
        $this->assertSame([], $views[0]['stack']);
        $this->assertCount(1, $views[0]['layers']['below']);
    }

    public function test_the_band_model_is_gone_from_the_editor(): void
    {
        $page = $this->actingAs($this->admin)->get(route('box.edit', $this->box->slug))->assertOk();
        $page->assertDontSee('data-sep="1"', false);
        $page->assertDontSee("'Fotonun altında'], ['above'", false);
        $page->assertSee('stackBottomUp', false);
    }
}
