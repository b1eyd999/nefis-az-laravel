<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * Seven designs reached the shelf with no description at all.
 *
 * The catalogue card showed a name and nothing else, and the product feed
 * fell back to a sentence built from the name — which is what Google was
 * reading for them. The texts are in database/data/design-descriptions-2026-10.php,
 * written from what is drawn on each box and from what its own customise
 * page offers.
 *
 * Only an empty description is filled. If the owner has since written his
 * own — in the admin, where all of this is editable — a deploy must not
 * walk over it, and this migration runs on every deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (require database_path('data/design-descriptions-2026-10.php') as $slug => $said) {
            $design = Product::where('slug', $slug)->first();

            if (! $design || filled($design->description)) {
                continue;
            }

            $design->description = $said['az'];

            /* Merged, not set: setTranslations replaces everything written
               in that language, so handing it the description alone would
               take the design's Russian name away with it. */
            foreach (['ru', 'en'] as $locale) {
                $design->setTranslations($locale, array_merge(
                    $design->translationsFor($locale),
                    ['description' => $said[$locale]],
                ));
            }

            $design->save();
        }
    }

    public function down(): void
    {
        // The descriptions stay. Taking them away again would leave the
        // catalogue emptier than this migration found it only if the owner
        // had not edited them, and there is no way to tell from here.
    }
};
