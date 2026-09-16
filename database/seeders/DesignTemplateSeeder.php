<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductAngle;
use Illuminate\Database\Seeder;

/**
 * Applies the artwork and slot geometry exported from the master PSDs
 * (database/seeders/data/design_templates.json). Entries carrying a "create"
 * block are also able to rebuild the product from scratch, so a wiped
 * products table can be restored by re-running this seeder.
 */
class DesignTemplateSeeder extends Seeder
{
    private const VIEW_FIELDS = [
        'template_image', 'overlay_image', 'template_width', 'template_height',
        'background_image', 'background_width', 'background_height',
        'box_area_x', 'box_area_y', 'box_area_width', 'box_area_height', 'box_area_rotation',
        'content_x', 'content_y', 'content_width', 'content_height', 'content_rotation',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/design_templates.json');

        if (! is_file($path)) {
            $this->command?->error("Missing {$path}");

            return;
        }

        $templates = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        foreach ($templates as $config) {
            $product = Product::where('slug', $config['slug'])->first();

            if (! $product) {
                if (empty($config['create'])) {
                    $this->command?->warn("Design not found: {$config['slug']}");
                    continue;
                }
                $product = new Product(['slug' => $config['slug']]);
                $product->slug = $config['slug'];
            }

            $product->fill($config['create'] ?? []);
            $product->fill($this->viewFields($config));
            $product->save();

            $this->syncSlots($product, $config);

            $product->angles()->each(function (ProductAngle $angle) {
                $angle->photoSlots()->delete();
                $angle->textSlots()->delete();
                $angle->delete();
            });

            foreach ($config['angles'] ?? [] as $order => $angleConfig) {
                $angle = $product->angles()->create($this->viewFields($angleConfig) + [
                    'label' => $angleConfig['label'] ?? null,
                    'sort_order' => $angleConfig['sort_order'] ?? $order,
                ]);
                $this->syncSlots($angle, $angleConfig);
            }

            $this->command?->info(sprintf(
                '%-30s photos=%d texts=%d angles=%d',
                $config['slug'],
                count($config['photo_slots']),
                count($config['text_slots']),
                count($config['angles'] ?? [])
            ));
        }
    }

    private function viewFields(array $config): array
    {
        return array_intersect_key($config, array_flip(self::VIEW_FIELDS));
    }

    private function syncSlots(Product|ProductAngle $owner, array $config): void
    {
        $owner->photoSlots()->delete();
        foreach ($config['photo_slots'] as $order => $slot) {
            $owner->photoSlots()->create($slot + ['sort_order' => $order]);
        }

        $owner->textSlots()->delete();
        foreach ($config['text_slots'] as $order => $slot) {
            $owner->textSlots()->create($slot + ['sort_order' => $order]);
        }
    }
}
