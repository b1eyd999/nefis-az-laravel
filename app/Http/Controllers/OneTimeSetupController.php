<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

// Müvəqqəti: shell girişi olmayan hostingdə migrasiya/quraşdırma üçün.
// İstifadədən sonra bu fayl və ona aid route silinməlidir.
class OneTimeSetupController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('ONE_TIME_SETUP_SECRET_2', ''), $secret)) {
            abort(404);
        }

        $out = [];

        Artisan::call('migrate', ['--force' => true]);
        $out[] = Artisan::output();

        if (! Product::where('slug', 'test-box')->exists()) {
            $im = imagecreatetruecolor(1000, 1000);
            $bg = imagecolorallocate($im, 230, 200, 160);
            imagefill($im, 0, 0, $bg);
            $border = imagecolorallocate($im, 58, 38, 23);
            imagerectangle($im, 250, 250, 750, 750, $border);
            imagestring($im, 5, 370, 380, 'PHOTO AREA', $border);
            ob_start();
            imagepng($im);
            $contents = ob_get_clean();
            imagedestroy($im);

            Storage::disk('public')->put('products/test-box.png', $contents);

            Product::create([
                'name' => 'Test Box',
                'slug' => 'test-box',
                'description' => 'Sınaq üçün nümunə qutu — canlı mokupu sınamaq üçündür.',
                'tag' => 'Demo',
                'price' => 25,
                'template_image' => 'products/test-box.png',
                'photo_area_x' => 250,
                'photo_area_y' => 250,
                'photo_area_width' => 500,
                'photo_area_height' => 500,
                'photo_area_rotation' => 0,
                'template_width' => 1000,
                'template_height' => 1000,
                'allow_text' => true,
                'text_x' => 500,
                'text_y' => 900,
                'text_max_width' => 600,
                'text_font_size' => 40,
                'text_color' => '#3A2617',
                'text_align' => 'center',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $out[] = 'Demo product created.';
        } else {
            $out[] = 'Demo product already exists.';
        }

        return response('<pre>'.e(implode("\n", $out)).'</pre>');
    }
}
