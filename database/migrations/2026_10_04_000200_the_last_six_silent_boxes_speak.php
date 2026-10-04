<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The six designs still standing on the shelf without a word about them.
 *
 * A box with no description shows the shelf's name on its catalogue card
 * instead — "Alyonka qutuları", "Şokolad Dizaynları" — and gives a search
 * engine a sentence the shop generated rather than one the owner meant. These
 * were the last six of sixty.
 *
 * Each line was written from the design itself: what the customer fills in,
 * and what is already printed on the box.
 */
return new class extends Migration
{
    /** slug => [Azerbaijani, Russian, English] */
    private const WORDS = [
        'alyonka-qar-qiz' => [
            '«Qar qızı» görkəmində «Alyonka»: üzünüz kəlağayının içinə düşür, naxışlı tor isə alnın üstündən keçir. Altında ad və öz yazdığınız söz. Yeni il hədiyyəsi üçün.',
            '«Алёнка» в образе Снегурочки: ваше лицо оказывается внутри платка, а узорная сетка проходит поверх лба. Снизу — имя и ваша надпись. Новогодний подарок.',
            'The "Alyonka" wrapper as the Snow Maiden: your face sits inside the headscarf and its lace runs across the forehead. A name and a line of your own underneath — a present for the new year.',
        ],
        'cici-bebe-boz' => [
            'Boz «Cici bebe» qablaşdırması: şəklinizdən yalnız baş götürülür və ortadakı mavi dairəyə oturur. Altında ad və özünüzün yazdığı zarafat.',
            'Серая упаковка «Cici bebe»: от фотографии остаётся только голова и садится в синий круг посередине. Снизу — имя и ваша шутка.',
            'The grey "Cici bebe" wrapper: only the head is taken from your photograph and set into the blue circle in the middle. A name and a joke of your own underneath.',
        ],
        'kinoya-gedek' => [
            '«Kinoya gedək?» — dəvət qutusu: kinolent, bilet və «Film səndən, biletlər məndən» yazısı. Doldurmağa heç nə yoxdur, sadəcə içindəki şokoladı seçin.',
            '«Kinoya gedək?» — коробка-приглашение: плёнка, билет и надпись «фильм с тебя, билеты с меня». Заполнять ничего не нужно, только выбрать шоколад внутри.',
            '"Shall we go to the cinema?" — a box that does the asking: a reel, a ticket and the line "the film is on you, the tickets on me". Nothing to fill in; just choose the bar inside.',
        ],
        'pampers' => [
            'Zarafat qutusu: dostunuzun üzü körpənin bədəninə oturur. Adi şəkil yükləyin — fonu sayt özü kəsir, altında isə ad və öz sözünüz.',
            'Шуточная коробка: лицо друга оказывается на теле младенца. Загрузите обычное фото — фон сайт срежет сам, снизу имя и ваша фраза.',
            'A joke box: your friend\'s face ends up on a baby\'s body. Upload an ordinary photograph — the site cuts the background away itself — with a name and a line of your own underneath.',
        ],
        'yasil-spotify' => [
            'Şəkliniz bütün qutunu tutur, altında isə yaşıl Spotify paneli: mahnının linkini yapışdırın, skan olunan kod qutuya çap olunur. Birlikdə dinlədiyiniz mahnı üçün.',
            'Ваше фото во всю коробку, снизу — зелёная панель Spotify: вставьте ссылку на песню, и сканируемый код напечатается на коробке. Для песни, которую вы слушали вместе.',
            'Your photograph fills the whole box, with the green Spotify panel beneath it: paste the song\'s link and the scannable code is printed on the box. For the song the two of you share.',
        ],
        'zencirli-box' => [
            'Şəkliniz əllə çəkilmiş tikanlı məftil çərçivəsinin içində, altında isə bir cümləniz. Sərt çərçivə və yumşaq söz — bir yerdə yaxşı oturur.',
            'Ваше фото в рамке из нарисованной от руки колючей проволоки, снизу — одна ваша фраза. Жёсткая рамка и мягкие слова рядом смотрятся хорошо.',
            'Your photograph inside a hand-drawn barbed-wire frame, with one line of yours beneath it. A hard frame around soft words sits better than you would think.',
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
