<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * The catalogue in three languages.
 *
 * The owner writes every design in Azerbaijani; this fills the Russian and
 * English beside it, and only where he has not written them himself — a
 * word he typed always wins over a word shipped in a file. Run again, it
 * changes nothing it has already done.
 */
return new class extends Migration
{
    public function up(): void
    {
        $all = require database_path('data/design-translations.php');

        foreach (Product::whereIn('slug', array_keys($all))->get() as $product) {
            $changed = false;

            foreach ($all[$product->slug] as $locale => $fields) {
                $written = $product->translationsFor($locale);

                foreach ($fields as $field => $value) {
                    if (blank($written[$field] ?? null)) {
                        $written[$field] = $value;
                        $changed = true;
                    }
                }

                $product->setTranslations($locale, $written);
            }

            if ($changed) {
                $product->saveQuietly();
            }
        }
    }

    public function down(): void
    {
        // Nothing: the Azerbaijani was never touched, and taking a
        // translation away again would only undo the owner's own edits.
    }
};
