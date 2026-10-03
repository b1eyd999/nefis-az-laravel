<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Words for the six boxes put up on 2026-10-04.
 *
 * A design with no description shows a bare heading to a customer and nothing
 * at all to a search engine — the catalogue card, the page and the search
 * result all read the same field. These six went up without one.
 *
 * Nothing the owner wrote himself is touched: a box that already has a
 * description is passed over, in every language separately.
 */
return new class extends Migration
{
    /** slug => [Azerbaijani, Russian, English] */
    private const WORDS = [
        'cici-bebe-sari2' => [
            'Sarı «Cici bebe» qablaşdırması, bu dəfə üz kəsimi ilə: şəklinizdən yalnız baş götürülür və hazır bədənin üstünə oturur. Altında ad və öz yazdığınız söz.',
            'Жёлтая упаковка «Cici bebe», теперь с вырезанием лица: от фотографии остаётся только голова и садится на нарисованное тело. Снизу — имя и ваша фраза.',
            'The yellow "Cici bebe" wrapper, this time with the face cut out: only the head is taken from your photograph and set on the drawn body. A name and a line of your own underneath.',
        ],
        'love-is-blue2' => [
            'Mavi «Love is…»: skamyada oturan tanış cütlük və sizin yazdığınız söz. Sakit rəng, gündəlik kiçik hədiyyə üçün.',
            'Синий «Love is…»: та самая парочка на скамейке и ваша надпись. Спокойный цвет, для маленького подарка без повода.',
            'The blue "Love is…": the couple on the bench everybody knows, and your own line. A quiet colour for a small gift on no occasion at all.',
        ],
        'love-is-mix' => [
            'Rəngli zolaqlı «Love is…»: mavi, yaşıl, çəhrayı, sarı və narıncı yan-yana. Ən şən variantı, altında hədiyyə verdiyiniz şəxsin adı.',
            'Полосатый «Love is…»: синий, зелёный, розовый, жёлтый и оранжевый рядом. Самый весёлый вариант, снизу — имя того, кому даришь.',
            'The striped "Love is…": blue, green, pink, yellow and orange side by side. The cheerfullest of them, with the name of whoever it is for underneath.',
        ],
        'love-is-orange2' => [
            'Narıncı «Love is…», isti və diqqət çəkən rəng. Altında hədiyyə verdiyiniz şəxsin adı yazılır.',
            'Оранжевый «Love is…» — тёплый и заметный. Снизу печатается имя того, кому даришь.',
            'The orange "Love is…", warm and hard to miss. The name of whoever it is for is printed underneath.',
        ],
        'love-is-red3' => [
            'Qırmızı «Love is…»: ürəyin öz rəngi. 14 Fevral və sevgi etirafı üçün ən uyğunu, altında isə adı.',
            'Красный «Love is…» — цвет самого сердца. Лучший для 14 февраля и признания, снизу — имя.',
            'The red "Love is…", the heart\'s own colour. The one for the fourteenth of February and for saying it out loud, with the name underneath.',
        ],
        'love-is-yellow' => [
            'Sarı «Love is…»: günəşli, uşaq kimi sevincli variant. Dosta və ya əhvalını qaldırmaq istədiyiniz adama.',
            'Жёлтый «Love is…» — солнечный, по-детски радостный. Другу или тому, кому хочется поднять настроение.',
            'The yellow "Love is…", sunny and childishly glad. For a friend, or for somebody whose day needs lifting.',
        ],
    ];

    public function up(): void
    {
        if (DB::table('products')->count() === 0) {
            return;                         // a fresh install, and every test database
        }

        foreach (self::WORDS as $slug => [$az, $ru, $en]) {
            $box = Product::where('slug', $slug)->first();
            if (! $box) {
                echo "  words: {$slug} is not here\n";

                continue;
            }

            $wrote = [];
            if (blank($box->description)) {
                $box->description = $az;
                $wrote[] = 'az';
            }
            foreach (['ru' => $ru, 'en' => $en] as $locale => $text) {
                if (blank(data_get($box->i18n, $locale . '.description'))) {
                    $box->setTranslations($locale, ['description' => $text] + $box->translationsFor($locale));
                    $wrote[] = $locale;
                }
            }

            if ($wrote) {
                $box->save();
            }
            echo "  words: {$slug} — " . ($wrote ? implode(', ', $wrote) : 'already written, left alone') . "\n";
        }
    }

    public function down(): void
    {
        // Left as it is: by now the owner may have edited any of these himself.
    }
};
