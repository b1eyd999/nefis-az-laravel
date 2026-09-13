<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductAngle;
use Illuminate\Support\Facades\Artisan;

// Müvəqqəti: shell girişi olmayan hostingdə "Klassik Şokolad Qutusu" məhsulunu
// və bucaqlarını yaratmaq üçün. İstifadədən sonra bu fayl və ona aid route silinməlidir.
class SeedClassicBoxController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('SEED_CLASSIC_BOX_SECRET', ''), $secret)) {
            abort(404);
        }

        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        $product = Product::updateOrCreate(
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

        $angles = [
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

        foreach ($angles as $data) {
            ProductAngle::updateOrCreate(
                ['product_id' => $product->id, 'label' => $data['label']],
                array_merge($data, ['product_id' => $product->id])
            );
        }

        return response("<pre>".e($migrateOutput)."</pre>Product ID: ".$product->id.' / Angles: '.$product->angles()->count());
    }
}
