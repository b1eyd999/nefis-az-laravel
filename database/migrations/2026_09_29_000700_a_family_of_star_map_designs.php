<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Eight ready star-map designs, so the shop has the family on the shelf
 * instead of the owner drawing each one by hand in the editor.
 *
 * There is no SSH on this hosting: a migration is the only thing that runs
 * on the server at all, so this is where content like this has to live. It
 * makes nothing that is not already possible in the editor — a background
 * shape, a window set to the sky, and five captions — and every design is
 * left switched OFF, because what a thing costs and whether it is for sale
 * are the owner's to say, not a deploy's.
 *
 * Nothing happens on a shop that has no products yet (a fresh install, and
 * every test database), so the suite is untouched.
 */
return new class extends Migration
{
    private const W = 969;

    private const H = 1895;

    /**
     * name, slug, sky style, shape, ring, page colour, ink colour, milky way.
     */
    private const FAMILY = [
        ['Ulduz xəritəsi — Klassik qara', 'ulduz-klassik-qara', 'night', 'ellipse', 'degrees', '#0b0b0d', '#ffffff', false],
        ['Ulduz xəritəsi — Klassik ağ', 'ulduz-klassik-ag', 'ink', 'ellipse', 'degrees', '#ffffff', '#111114', false],
        ['Ulduz xəritəsi — Ürək', 'ulduz-urek', 'night', 'heart', 'none', '#0b0b0d', '#ffffff', false],
        ['Ulduz xəritəsi — Retro göy', 'ulduz-retro-goy', 'navy', 'ellipse', 'double', '#1b3a5c', '#e8f1fa', false],
        ['Ulduz xəritəsi — Retro al', 'ulduz-retro-al', 'crimson', 'ellipse', 'double', '#8c1626', '#fbe9ec', false],
        ['Ulduz xəritəsi — Retro krem', 'ulduz-retro-krem', 'cream', 'ellipse', 'double', '#f4e6cd', '#4a3520', false],
        ['Ulduz xəritəsi — Kosmos', 'ulduz-kosmos', 'cosmos', 'full', 'none', '#ffffff', '#111114', true],
        ['Ulduz xəritəsi — Yaşıl duman', 'ulduz-yasil-duman', 'moss', 'full', 'none', '#ffffff', '#111114', true],
    ];

    public function up(): void
    {
        if (DB::table('products')->count() === 0) {
            return;                     // a fresh install has nothing to stand beside
        }

        // Priced and filed like the designs already on sale, so the cards do
        // not look out of place; the owner corrects both before switching one on.
        $price = DB::table('products')->where('is_active', true)->whereNotNull('price')->avg('price');
        $category = DB::table('products')->where('is_active', true)->whereNotNull('category')->value('category');
        $font = DB::table('fonts')->orderBy('id')->first();

        foreach (self::FAMILY as [$name, $slug, $style, $shape, $ring, $page, $ink, $milky]) {
            if (Product::where('slug', $slug)->exists()) {
                continue;               // already here: never overwrite the owner's work
            }

            $product = Product::create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => false,
                'price' => $price ? round((float) $price, 2) : null,
                'category' => $category,
                'template_width' => self::W,
                'template_height' => self::H,
                'description' => 'O gecə, o yerin üstündəki səma — tarixi, saatı və yeri müştəri özü seçir.',
            ]);

            // The card itself: one filled rectangle under everything.
            $product->shapes()->create([
                'kind' => 'rect', 'x' => 0, 'y' => 0, 'width' => self::W, 'height' => self::H,
                'rotation' => 0, 'fill' => $page, 'stroke_color' => null, 'stroke_width' => 0,
                'radius' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0,
            ]);

            // The sky. A full-bleed one covers the whole face and fades into
            // the page on its own; a disc sits in the upper half.
            $product->photoSlots()->create([
                'label' => null,
                'fill' => 'sky',
                'sky_style' => $style,
                'sky_ring' => true,
                'sky_ring_kind' => $ring,
                'sky_choices' => 'lines,labels,milky,heart,time',
                'sky_lines' => true,
                'sky_labels' => false,
                'sky_milky' => $milky,
                'shape' => $shape,
                'x' => $shape === 'full' ? 0 : 85,
                'y' => $shape === 'full' ? 0 : 170,
                'width' => $shape === 'full' ? self::W : 800,
                'height' => $shape === 'full' ? self::H : 800,
                'rotation' => 0,
                'sort_order' => 0,
            ]);

            foreach ($this->captions($ink, $font) as $order => $caption) {
                $product->textSlots()->create($caption + ['sort_order' => $order]);
            }
        }
    }

    public function down(): void
    {
        Product::whereIn('slug', array_column(self::FAMILY, 1))->each(fn (Product $p) => $p->delete());
    }

    /**
     * The block under the sky: three lines the customer writes, then the
     * coordinates and the date, which the map fills in by itself.
     */
    private function captions(string $ink, ?object $font): array
    {
        $base = [
            'kind' => 'text',
            'fixed' => false,
            'x' => (int) (self::W / 2),
            'max_width' => 860,
            'color' => $ink,
            'align' => 'center',
            'rotation' => 0,
            'max_lines' => 1,
            'font_family' => $font->family ?? null,
            'font_file' => $font->file ?? null,
            'font_weight' => $font->weight ?? 400,
            'line_height' => 120,
            'scale_x' => 100,
            'scale_y' => 100,
            'baseline_shift' => 0,
            'stroke_color' => null,
            'stroke_width' => 0,
            'text_case' => 'upper',
            'auto' => 'none',
        ];

        return [
            array_merge($base, ['label' => 'Üst yazı', 'default_value' => 'Where it all began', 'y' => 1120,
                'font_size' => 44, 'tracking' => 300, 'max_length' => 40]),
            array_merge($base, ['label' => 'Ad', 'default_value' => 'Caspianda', 'y' => 1250,
                'font_size' => 108, 'tracking' => 20, 'max_length' => 24]),
            array_merge($base, ['label' => 'İkinci sətir', 'default_value' => 'Friends Coffee', 'y' => 1350,
                'font_size' => 58, 'tracking' => 120, 'max_length' => 30]),
            array_merge($base, ['label' => 'Koordinatlar', 'default_value' => null, 'y' => 1470,
                'font_size' => 32, 'tracking' => 60, 'max_length' => 40, 'auto' => 'coords', 'text_case' => 'none']),
            array_merge($base, ['label' => 'Tarix', 'default_value' => null, 'y' => 1540,
                'font_size' => 28, 'tracking' => 200, 'max_length' => 40, 'auto' => 'date_long']),
        ];
    }
};
