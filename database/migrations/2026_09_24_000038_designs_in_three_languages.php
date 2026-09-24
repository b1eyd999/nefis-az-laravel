<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * The Russian and English words for each design. Only what the owner has left
 * empty is filled: anything he has written himself is never touched, and a
 * design that is not on the site simply has nothing to fill.
 */
return new class extends Migration
{
    public function up(): void
    {
        $words = require database_path('data/design-descriptions-ru-en.php');

        foreach ($words as $slug => $languages) {
            $design = Product::where('slug', $slug)->first();
            if (! $design) {
                continue;
            }

            $i18n = (array) $design->i18n;
            foreach ($languages as $locale => $fields) {
                foreach ($fields as $field => $text) {
                    if (blank(data_get($i18n, $locale . '.' . $field))) {
                        $i18n[$locale][$field] = $text;
                    }
                }
            }

            $design->forceFill(['i18n' => $i18n])->saveQuietly();
        }
    }

    public function down(): void
    {
        // The owner's own wording lives in the same column; nothing is undone.
    }
};
