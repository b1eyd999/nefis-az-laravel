<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;

/**
 * The newest design went up without a description, so its card in the
 * catalogue said nothing but the name. It takes one photo and no captions,
 * and the words say exactly that.
 *
 * Written only into an empty field: anything the owner types himself stays.
 */
return new class extends Migration
{
    private const AZ = 'Əsl «Alyonka» bükümü: qızıl yazı, tünd fon və kəlağayın içində sizin şəkliniz. '
        . 'Mətn yoxdur, yalnız şəkil — uşaqlıqdan tanış dizaynı sevən anaya, bacıya və rəfiqəyə.';

    private const OTHER = [
        'ru' => 'Настоящая обёртка «Алёнка»: золотая надпись, тёмный фон и ваше фото в платке. '
            . 'Без надписей, только фото — маме, сестре и подруге, которые любят этот знакомый с детства дизайн.',
        'en' => 'The real Alyonka wrapper: the gold lettering, the dark background and your photo inside the headscarf. '
            . 'No captions, just the photo — for a mother, a sister or a friend who grew up with this design.',
    ];

    public function up(): void
    {
        $product = Product::where('slug', 'alyonka-origin')->first();

        if (! $product) {
            return;
        }

        $changed = false;

        if (blank($product->description)) {
            $product->description = self::AZ;
            $changed = true;
        }

        foreach (self::OTHER as $locale => $text) {
            $written = $product->translationsFor($locale);

            if (blank($written['description'] ?? null)) {
                $written['description'] = $text;
                $product->setTranslations($locale, $written);
                $changed = true;
            }
        }

        if ($changed) {
            $product->saveQuietly();
        }
    }

    public function down(): void
    {
        // Nothing: taking a description away again would only leave a blank card.
    }
};
