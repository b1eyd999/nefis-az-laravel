<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The three sizes the owner sells, as he dictated them.
 *
 * Measured as he measures them — width, length, depth — of which the drawing
 * and the page need the first two: the printed face. The depth is in the
 * sentence the page shows because a company ordering five hundred of these
 * wants to know how thick the thing is.
 *
 *   85 x 160 x 10 mm
 *   85 x  85 x 10 mm
 *   40 x  60 x 15 mm
 *
 * Their names are plain shapes rather than anything about the chocolate
 * inside: how many pieces a box holds is his to say, and a name invented
 * here would go live as a fact. He renames them in the admin in a moment.
 *
 * Said again on a second deploy, or edited by hand meanwhile: a size whose
 * name is already there is left exactly as it is. Nothing here overwrites
 * his own words.
 *
 * The renders are deliberately empty. Until a picture of a box exists the
 * page draws the box itself, in the colour the visitor picked, at these
 * proportions — so the three sizes work today and get better when the
 * Blender renders arrive.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ($this->sizes() as $size) {
            if (DB::table('box_sizes')->where('name', $size['name'])->exists()) {
                continue;
            }

            /* array_merge, not «+»: the right side has to win. With «+» the
               i18n below would lose to the array already in $size and the
               insert would be handed an array where a JSON string belongs. */
            DB::table('box_sizes')->insert(array_merge($size, [
                'i18n' => json_encode($size['i18n'], JSON_UNESCAPED_UNICODE),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        /* The one size that was in there was put there from this side as an
           example, an hour before he said what the real three are. It goes —
           but only while it is still untouched: edited, it is his, and he
           deletes it himself if he wants to. */
        DB::table('box_sizes')
            ->where('name', 'Bir dilim')
            ->where('dimensions', '4,5 × 9 × 1,5 sm — bir dilim şokolad')
            ->delete();
    }

    public function down(): void
    {
        DB::table('box_sizes')
            ->whereIn('name', array_column($this->sizes(), 'name'))
            ->delete();
    }

    /** @return array<int, array<string, mixed>> */
    private function sizes(): array
    {
        return [
            [
                'name' => 'Uzun',
                'dimensions' => '8,5 × 16 × 1 sm',
                'width_mm' => 85,
                'height_mm' => 160,
                'sort_order' => 1,
                'i18n' => [
                    'ru' => ['name' => 'Длинная', 'dimensions' => '8,5 × 16 × 1 см'],
                    'en' => ['name' => 'Long', 'dimensions' => '8.5 × 16 × 1 cm'],
                ],
            ],
            [
                'name' => 'Kvadrat',
                'dimensions' => '8,5 × 8,5 × 1 sm',
                'width_mm' => 85,
                'height_mm' => 85,
                'sort_order' => 2,
                'i18n' => [
                    'ru' => ['name' => 'Квадратная', 'dimensions' => '8,5 × 8,5 × 1 см'],
                    'en' => ['name' => 'Square', 'dimensions' => '8.5 × 8.5 × 1 cm'],
                ],
            ],
            [
                'name' => 'Kiçik',
                'dimensions' => '4 × 6 × 1,5 sm',
                'width_mm' => 40,
                'height_mm' => 60,
                'sort_order' => 3,
                'i18n' => [
                    'ru' => ['name' => 'Маленькая', 'dimensions' => '4 × 6 × 1,5 см'],
                    'en' => ['name' => 'Small', 'dimensions' => '4 × 6 × 1.5 cm'],
                ],
            ],
        ];
    }
};
