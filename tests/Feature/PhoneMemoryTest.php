<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A photograph off a phone is 12 to 48 megapixels. Decoded whole it is tens
 * or hundreds of megabytes, and iOS Safari answers that by throwing the page
 * away and loading it again — which is what a customer saw a few seconds
 * after choosing a picture, twice now.
 *
 * Every place that opens a customer's photograph must go through
 * NefisPhoto, which asks the browser to decode straight to the size wanted.
 */
class PhoneMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_that_takes_a_photo_loads_the_shrinker_first(): void
    {
        Setting::put(Setting::LETTER_ENABLED, '1');      // the letter page has a photo too

        $box = Product::create(['name' => 'Test', 'slug' => 'test-box', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $box->photoSlots()->create(['label' => 'Üz', 'x' => 10, 'y' => 10, 'width' => 400, 'height' => 500,
            'rotation' => 0, 'shape' => 'rect', 'cutout' => false, 'sort_order' => 0]);

        foreach ([route('products.customize', $box->slug), route('letters.create')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('js/photo-shrink.js', $html, "$url opens photographs");

            // …and before whatever uses it, or the first picture still goes the old way.
            foreach (['js/polaroid.js', 'js/face-cutout.js'] as $user) {
                if (str_contains($html, $user)) {
                    $this->assertLessThan(strpos($html, $user), strpos($html, 'js/photo-shrink.js'),
                        "photo-shrink.js must come before $user");
                }
            }
        }
    }

    public function test_no_one_reads_a_whole_photograph_into_a_string_any_more(): void
    {
        $shrinker = file_get_contents(public_path('js/photo-shrink.js'));
        $this->assertStringContainsString('createImageBitmap', $shrinker, 'the browser decodes at the size asked for');
        $this->assertStringContainsString('resizeWidth', $shrinker);

        foreach (['js/polaroid.js', 'js/face-cutout.js'] as $file) {
            $js = file_get_contents(public_path($file));
            $this->assertStringContainsString('NefisPhoto', $js, "$file must go through the shrinker");
        }

        // The customize page kept its own copy of this trap once; it is gone.
        $page = file_get_contents(resource_path('views/products/customize.blade.php'));
        $this->assertStringNotContainsString('readAsDataURL', $page);
        $this->assertStringContainsString('NefisPhoto.load(file, PREVIEW_MAX)', $page);
    }
}
