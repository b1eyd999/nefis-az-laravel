<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAngle;

// Müvəqqəti: shell girişi olmayan hostingdə "Alyonka Azerbaijan Style"
// məhsulunu yaratmaq üçün. İstifadədən sonra silinməlidir.
class SeedAlyonkaController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('SEED_ALYONKA_SECRET', ''), $secret)) {
            abort(404);
        }

        $product = Product::updateOrCreate(
            ['slug' => 'alyonka-azerbaijan-style'],
            [
                'name' => 'Alyonka Azerbaijan Style',
                'description' => 'Milli kəlağayı çərçivəsində fərdi portret — öz şəklinizi yükləyin, üzünüz oval çərçivənin içində əks olunsun.',
                'tag' => null,
                'price' => null,
                'template_image' => 'products/alyonka-front.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 465,
                'photo_area_y' => 788,
                'photo_area_width' => 583,
                'photo_area_height' => 824,
                'photo_area_rotation' => 175,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 757,
                'text_y' => 1200,
                'text_max_width' => 500,
                'text_font_size' => 60,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $angles = [
            [
                'label' => 'Açıq qutu',
                'template_image' => 'products/alyonka-angle-open.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 491,
                'photo_area_y' => 736,
                'photo_area_width' => 268,
                'photo_area_height' => 421,
                'photo_area_rotation' => 168,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 625,
                'text_y' => 946,
                'text_max_width' => 240,
                'text_font_size' => 30,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 1,
            ],
            [
                'label' => 'Əyri bucaq',
                'template_image' => 'products/alyonka-angle-diagonal.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 441,
                'photo_area_y' => 783,
                'photo_area_width' => 268,
                'photo_area_height' => 414,
                'photo_area_rotation' => 111,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 575,
                'text_y' => 990,
                'text_max_width' => 240,
                'text_font_size' => 30,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 2,
            ],
            [
                'label' => 'Dik profil',
                'template_image' => 'products/alyonka-angle-upright.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 646,
                'photo_area_y' => 913,
                'photo_area_width' => 395,
                'photo_area_height' => 596,
                'photo_area_rotation' => 2,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 844,
                'text_y' => 1211,
                'text_max_width' => 350,
                'text_font_size' => 45,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 3,
            ],
        ];

        foreach ($angles as $data) {
            ProductAngle::updateOrCreate(
                ['product_id' => $product->id, 'label' => $data['label']],
                array_merge($data, ['product_id' => $product->id])
            );
        }

        return response('Product ID: '.$product->id.' / Angles: '.$product->angles()->count());
    }
}
