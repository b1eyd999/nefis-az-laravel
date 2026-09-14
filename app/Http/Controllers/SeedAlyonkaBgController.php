<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAngle;
use Illuminate\Support\Facades\Artisan;

// Müvəqqəti: products cədvəli boş çıxdığı üçün (əvvəlki seed məlumatları
// itib) hər iki məhsulu sıfırdan bərpa edir, sonra Alyonka üçün arxa fon/
// box_area/content_box sahələrini yazır.
class SeedAlyonkaBgController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('SEED_ALYONKA_BG_SECRET', ''), $secret)) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);
        $out = Artisan::output();

        // --- Klassik Şokolad Qutusu ---
        $classic = Product::updateOrCreate(
            ['slug' => 'klassik-qutu'],
            [
                'name' => 'Klassik Şokolad Qutusu',
                'description' => 'Öz şəklinizi və mesajınızı əlavə edin, biz onu şokolad qutusunun üzərinə köçürək.',
                'tag' => null,
                'price' => null,
                'template_image' => 'products/box-classic-front.png',
                'template_width' => 3080,
                'template_height' => 3850,
                'photo_area_x' => -109,
                'photo_area_y' => 1094,
                'photo_area_width' => 3228,
                'photo_area_height' => 2276,
                'photo_area_rotation' => -90,
                'allow_text' => true,
                'text_x' => 1505,
                'text_y' => 2232,
                'text_max_width' => 1800,
                'text_font_size' => 130,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $classicAngles = [
            [
                'label' => 'Sınıq şokolad',
                'template_image' => 'products/box-classic-angle-break.png',
                'template_width' => 3080,
                'template_height' => 3850,
                'photo_area_x' => 751,
                'photo_area_y' => 956,
                'photo_area_width' => 1064,
                'photo_area_height' => 2384,
                'photo_area_rotation' => -9,
                'allow_text' => true,
                'text_x' => 1283,
                'text_y' => 2148,
                'text_max_width' => 900,
                'text_font_size' => 100,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 1,
            ],
            [
                'label' => 'Metal fon',
                'template_image' => 'products/box-classic-angle-metal.png',
                'template_width' => 3080,
                'template_height' => 3850,
                'photo_area_x' => 875,
                'photo_area_y' => 857,
                'photo_area_width' => 1076,
                'photo_area_height' => 2324,
                'photo_area_rotation' => -66,
                'allow_text' => true,
                'text_x' => 1413,
                'text_y' => 2019,
                'text_max_width' => 900,
                'text_font_size' => 100,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 2,
            ],
            [
                'label' => 'Dik profil',
                'template_image' => 'products/box-classic-angle-upright.png',
                'template_width' => 3080,
                'template_height' => 3850,
                'photo_area_x' => 310,
                'photo_area_y' => 1742,
                'photo_area_width' => 2672,
                'photo_area_height' => 1524,
                'photo_area_rotation' => -90,
                'allow_text' => true,
                'text_x' => 1646,
                'text_y' => 2504,
                'text_max_width' => 1800,
                'text_font_size' => 130,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 3,
            ],
        ];

        foreach ($classicAngles as $data) {
            ProductAngle::updateOrCreate(
                ['product_id' => $classic->id, 'label' => $data['label']],
                array_merge($data, ['product_id' => $classic->id])
            );
        }

        $out .= "classic box product id: {$classic->id}\n";

        // --- Alyonka Azerbaijan Style (rotasiyalar artıq -90..90 aralığında) ---
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
                'photo_area_rotation' => -5,
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
            ]
        );

        $angleData = [
            'Açıq qutu' => [
                'template_image' => 'products/alyonka-angle-open.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 491,
                'photo_area_y' => 736,
                'photo_area_width' => 268,
                'photo_area_height' => 421,
                'photo_area_rotation' => -12,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 625,
                'text_y' => 946,
                'text_max_width' => 240,
                'text_font_size' => 30,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 1,
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
                'template_image' => 'products/alyonka-angle-diagonal.png',
                'template_width' => 1600,
                'template_height' => 2000,
                'photo_area_x' => 441,
                'photo_area_y' => 783,
                'photo_area_width' => 268,
                'photo_area_height' => 414,
                'photo_area_rotation' => -69,
                'photo_area_shape' => 'ellipse',
                'allow_text' => true,
                'text_x' => 575,
                'text_y' => 990,
                'text_max_width' => 240,
                'text_font_size' => 30,
                'text_color' => '#FFFFFF',
                'text_align' => 'center',
                'sort_order' => 2,
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

        foreach ($angleData as $label => $data) {
            ProductAngle::updateOrCreate(
                ['product_id' => $product->id, 'label' => $label],
                array_merge($data, ['product_id' => $product->id])
            );
        }

        $out .= "alyonka product id: {$product->id} / angles: {$product->angles()->count()}\n";

        return response('<pre>'.e($out).'</pre>done');
    }
}
