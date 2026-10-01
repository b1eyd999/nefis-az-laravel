<?php

namespace Database\Seeders;

use App\Models\PhotoSlot;
use App\Models\Product;
use App\Models\TextSlot;
use Illuminate\Database\Seeder;

/**
 * A location box to work against on a developer's machine.
 *
 * Production has the owner's own eleven; this makes one locally so the window,
 * the captions and the print sheet can be seen without touching the live shop.
 * Safe to run more than once.
 */
class LokasiyaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::updateOrCreate(
            ['slug' => 'lokasiya-demo'],
            [
                'name' => 'Lokasiya (demo)',
                'description' => 'Yerin küçələri qutunun üzərində.',
                'price' => 14.9,
                'category' => 'xerite',
                'is_active' => true,
                'template_width' => 1000,
                'template_height' => 1600,
            ],
        );

        $product->photoSlots()->delete();
        $product->textSlots()->delete();

        $product->photoSlots()->create([
            'label' => 'Xəritə',
            'fill' => PhotoSlot::MAP,
            'map_style' => 'ink',
            'map_marker' => 'heart',
            'map_zoom' => 15,
            'map_choices' => 'zoom,pin',
            'map_pin' => true,
            'x' => 90, 'y' => 90, 'width' => 820, 'height' => 1000,
            'rotation' => 0, 'shape' => 'rectangle', 'sort_order' => 0,
        ]);

        foreach ([
            ['label' => 'Üst yazı', 'default_value' => 'WHERE IT ALL BEGAN', 'auto' => 'none', 'y' => 1160, 'size' => 34, 'fixed' => true],
            ['label' => 'Yerin adı', 'default_value' => '', 'auto' => 'place', 'y' => 1230, 'size' => 72, 'fixed' => false],
            ['label' => 'Koordinatlar', 'default_value' => '', 'auto' => 'coords', 'y' => 1320, 'size' => 28, 'fixed' => false],
            ['label' => 'Tarix', 'default_value' => '', 'auto' => 'date_long', 'y' => 1380, 'size' => 28, 'fixed' => false],
        ] as $order => $t) {
            $product->textSlots()->create([
                'label' => $t['label'],
                'kind' => TextSlot::KIND_TEXT,
                'auto' => $t['auto'],
                'fixed' => $t['fixed'],
                'default_value' => $t['default_value'],
                'x' => 500, 'y' => $t['y'],
                'max_width' => 820, 'font_size' => $t['size'],
                'color' => '#111111', 'align' => 'center', 'rotation' => 0,
                'max_lines' => 1, 'max_length' => 60, 'sort_order' => $order,
            ]);
        }

        $this->command?->info('Lokasiya (demo) is on the shelf: /products/lokasiya-demo/customize');
    }
}
