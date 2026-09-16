<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Turns catalog-only designs into customizable products using the artwork and
 * slot geometry exported from the master PSDs (database/seeders/data).
 */
class DesignTemplateSeeder extends Seeder
{
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
                $this->command?->warn("Design not found: {$config['slug']}");
                continue;
            }

            $product->update([
                'template_image' => $config['template_image'],
                'overlay_image' => $config['overlay_image'],
                'template_width' => $config['template_width'],
                'template_height' => $config['template_height'],
            ]);

            $product->photoSlots()->delete();
            foreach ($config['photo_slots'] as $order => $slot) {
                $product->photoSlots()->create($slot + ['sort_order' => $order]);
            }

            $product->textSlots()->delete();
            foreach ($config['text_slots'] as $order => $slot) {
                $product->textSlots()->create($slot + ['sort_order' => $order]);
            }

            $this->command?->info(sprintf(
                'Configured %-30s photos=%d texts=%d',
                $config['slug'],
                count($config['photo_slots']),
                count($config['text_slots'])
            ));
        }
    }
}
