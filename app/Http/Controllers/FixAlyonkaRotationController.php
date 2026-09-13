<?php

namespace App\Http\Controllers;

use App\Models\Product;

// Müvəqqəti: Alyonka məhsulunun photo_area_rotation dəyərlərini
// düzəltmək üçün (175/168/111 dərəcə -90..90 aralığına salınır).
// İstifadədən sonra silinməlidir.
class FixAlyonkaRotationController extends Controller
{
    public function run(string $secret)
    {
        if (! hash_equals(env('FIX_ALYONKA_SECRET', ''), $secret)) {
            abort(404);
        }

        $normalize = function (int $deg): int {
            while ($deg > 90) {
                $deg -= 180;
            }
            while ($deg <= -90) {
                $deg += 180;
            }

            return $deg;
        };

        $product = Product::where('slug', 'alyonka-azerbaijan-style')->firstOrFail();
        $out = [];

        $old = $product->photo_area_rotation;
        $product->update(['photo_area_rotation' => $normalize($old)]);
        $out[] = "product: {$old} -> {$product->photo_area_rotation}";

        foreach ($product->angles as $angle) {
            $old = $angle->photo_area_rotation;
            $angle->update(['photo_area_rotation' => $normalize($old)]);
            $out[] = "angle {$angle->label}: {$old} -> {$angle->photo_area_rotation}";
        }

        return response(implode("\n", $out));
    }
}
