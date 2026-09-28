<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\ImageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The catalogue cards are about 160 points wide on a phone while the posters
 * are 1080 across, so the card is served a copy cut to size. Pages only ever
 * serve a copy that already exists — making them is the deploy's job, so no
 * visitor waits on twenty-seven resizes.
 */
class CardPictureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function box(string $slug = 'love-story'): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => $slug, 'is_active' => true, 'price' => 6.5, 'category' => 'sokolad',
            'template_width' => 969, 'template_height' => 1895]);
        $box->forceFill(['poster_image' => 'boxes/' . $slug . '/poster.webp'])->save();
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/' . $slug . '/bg.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        // A poster the size the shop's own are.
        $canvas = imagecreatetruecolor(1080, 1350);
        imagefilledrectangle($canvas, 0, 0, 1080, 1350, imagecolorallocate($canvas, 180, 90, 40));
        ob_start();
        imagewebp($canvas, null, 85);
        Storage::disk('public')->put($box->poster_image, ob_get_clean());
        imagedestroy($canvas);

        return $box;
    }

    public function test_a_page_never_makes_a_copy_itself(): void
    {
        $box = $this->box();

        $this->assertNull($box->catalogImageSmall(), 'nothing is written while a page is being drawn');
        $this->get(route('designs.index'))->assertOk()->assertDontSee('srcset', false);

        Storage::disk('public')->assertMissing('boxes/love-story/poster@400.webp');
    }

    public function test_the_deploy_writes_the_copy_and_the_card_then_offers_both(): void
    {
        $box = $this->box();

        $this->artisan('products:card-pictures')->assertSuccessful();

        $small = 'boxes/love-story/poster@400.webp';
        Storage::disk('public')->assertExists($small);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($small));
        $this->assertSame(320, $w, 'the longest side is 400, so a 4:5 poster comes out 320 wide');
        $this->assertSame(400, $h);
        $this->assertLessThan(
            strlen(Storage::disk('public')->get($box->poster_image)),
            strlen(Storage::disk('public')->get($small)),
        );

        $this->get(route('designs.index'))->assertOk()
            ->assertSee('srcset', false)
            ->assertSee('poster@400.webp 400w', false)
            ->assertSee('sizes="(max-width: 760px) 45vw, 300px"', false);
    }

    public function test_running_it_again_leaves_the_copy_alone(): void
    {
        $this->box();

        $this->artisan('products:card-pictures')->assertSuccessful();
        $first = Storage::disk('public')->get('boxes/love-story/poster@400.webp');

        $this->artisan('products:card-pictures')->expectsOutputToContain('0 new.')->assertSuccessful();

        $this->assertSame($first, Storage::disk('public')->get('boxes/love-story/poster@400.webp'));
    }

    public function test_artwork_that_lives_elsewhere_is_left_as_it_is(): void
    {
        $box = $this->box();
        $box->forceFill(['poster_image' => null, 'preview_image' => 'boxes/love-story/nothing-here.webp'])->save();

        $this->assertNull($box->catalogImageSmall(400, true));
        $this->assertNull(ImageStore::smaller(null));
    }
}
