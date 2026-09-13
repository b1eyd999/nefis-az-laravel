<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;

// Müvəqqəti: arxa fon sahələrini migrasiya edib Alyonka məhsuluna
// arxa fon/box_area/content_box dəyərlərini yazmaq üçün.
class SeedAlyonkaBgController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('SEED_ALYONKA_BG_SECRET', ''), $secret)) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);
        $out = Artisan::output();

        $product = Product::where('slug', 'alyonka-azerbaijan-style')->firstOrFail();

        $product->update([
            'background_image' => 'backgrounds/alyonka-bg-front.png',
            'background_width' => 1600,
            'background_height' => 2000,
            'box_area_x' => -57,
            'box_area_y' => 568,
            'box_area_width' => 1677,
            'box_area_height' => 1182,
            'box_area_rotation' => -90,
            'content_x' => -59,
            'content_y' => 567,
            'content_width' => 1680,
            'content_height' => 1184,
            'content_rotation' => -90,
        ]);

        $angleData = [
            'Açıq qutu' => [
                'background_image' => 'backgrounds/alyonka-bg-open.png',
                'background_width' => 1600,
                'background_height' => 2000,
                'box_area_x' => 390,
                'box_area_y' => 497,
                'box_area_width' => 551,
                'box_area_height' => 1236,
                'box_area_rotation' => -9,
                'content_x' => 384,
                'content_y' => 506,
                'content_width' => 744,
                'content_height' => 1242,
                'content_rotation' => -9,
            ],
            'Əyri bucaq' => [
                'background_image' => 'backgrounds/alyonka-bg-diagonal.png',
                'background_width' => 1600,
                'background_height' => 2000,
                'box_area_x' => 455,
                'box_area_y' => 445,
                'box_area_width' => 559,
                'box_area_height' => 1207,
                'box_area_rotation' => -66,
                'content_x' => 449,
                'content_y' => 439,
                'content_width' => 565,
                'content_height' => 1218,
                'content_rotation' => -66,
            ],
            'Dik profil' => [
                'background_image' => 'backgrounds/alyonka-bg-upright.png',
                'background_width' => 1600,
                'background_height' => 2000,
                'box_area_x' => 160,
                'box_area_y' => 906,
                'box_area_width' => 1388,
                'box_area_height' => 790,
                'box_area_rotation' => -90,
                'content_x' => 119,
                'content_y' => 866,
                'content_width' => 1474,
                'content_height' => 799,
                'content_rotation' => -89,
            ],
        ];

        foreach ($product->angles as $angle) {
            if (isset($angleData[$angle->label])) {
                $angle->update($angleData[$angle->label]);
                $out .= "updated angle: {$angle->label}\n";
            }
        }

        return response('<pre>'.e($out).'</pre>done');
    }
}
