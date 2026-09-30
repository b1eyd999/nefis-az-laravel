<?php

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The star designs get words of their own.
 *
 * Ten of them went up in one evening, all under the same name, so in the
 * catalogue they were ten identical cards: nothing said which one is the
 * cream paper and which is the red disc. Each now carries a line that says
 * what it looks like and what the customer chooses, in all three languages.
 *
 * Nothing already written is touched: a design whose description the owner
 * has filled in keeps it, and so does every translation he wrote himself.
 */
return new class extends Migration
{
    /** slug => [Azerbaijani, Russian, English] */
    private const WORDS = [
        'burc-astronimiya' => [
            'Qara gecə səması dərəcəli halqanın içində — klassik ulduz xəritəsi. Tarixi, saatı və yeri siz seçirsiniz: o gecə həmin yerin üstündə hansı ulduzlar varsa, qutuya onlar düşür.',
            'Чёрное ночное небо в градусном кольце — классическая звёздная карта. Дату, время и место выбираете вы: на коробку попадут те звёзды, что стояли над этим местом той ночью.',
            'A black night sky inside a graduated ring — the classic star chart. You pick the date, the hour and the place, and the box gets the stars that stood over it that night.',
        ],
        'burc-astronimiya2' => [
            'Krem kağız üzərində qəhvəyi səma — köhnə astronomiya atlaslarının görkəmi. Doğum gecəsi, ilk görüş, toy günü: tarixi yazın, səma özü yerinə düşür.',
            'Кремовая бумага и коричневое небо — вид старого астрономического атласа. Ночь рождения, первое свидание, день свадьбы: укажите дату, и небо встанет само.',
            'Brown sky on cream paper, in the manner of an old astronomical atlas. A birth night, a first date, a wedding day: give the date and the sky falls into place.',
        ],
        'burc-astronimiya-blue' => [
            'Tünd mavi gecə, nazik halqa, ağ ulduzlar — sakit və bahalı görünüş. Adı, tarixi və yeri özünüz yazırsınız, koordinatlar avtomatik düşür.',
            'Тёмно-синяя ночь, тонкое кольцо, белые звёзды — спокойный, дорогой вид. Имя, дату и место пишете вы, координаты подставляются сами.',
            'A deep blue night, a thin ring, white stars — quiet and expensive-looking. You write the name, the date and the place; the coordinates fill themselves in.',
        ],
        'burc-astronimiya-blue-each' => [
            'Açıq mavi səma — gündüz kimi işıqlı, uşaq və ad günü hədiyyələri üçün. Ulduzlar isə həqiqidir: seçdiyiniz gecənin öz səmasıdır.',
            'Светло-голубое небо — светлое, как день; хорошо для детских подарков и дней рождения. А звёзды настоящие — небо именно той ночи, что вы выбрали.',
            'A light blue sky — bright as day, good for a child or a birthday. The stars are real all the same: the sky of the very night you choose.',
        ],
        'burc-astronimiya-green' => [
            'Yaşıl duman: Süd Yolu boyunca işıqlanan səma, aşağıda ağ kağıza qarışır. Künclərə qədər dolu düzbucaqlı — yazılar üçün aşağıda rahat yer qalır.',
            'Зелёная дымка: небо светится вдоль Млечного Пути и внизу растворяется в белом. Прямоугольник, залитый до углов, — внизу остаётся спокойное место для надписей.',
            'Green mist: the sky glows along the Milky Way and washes into white below. A rectangle filled to its corners, with a quiet place for the words underneath.',
        ],
        'burc-astronimiya-heard-black' => [
            'Ürək şəklində kəsilmiş gecə səması — sevgi hədiyyəsi üçün. Tanış olduğunuz gecəni seçin: ürəyin içində həmin gecənin ulduzları olacaq.',
            'Ночное небо, вырезанное сердцем, — для подарка любимому человеку. Выберите ночь вашего знакомства: внутри сердца будут звёзды именно той ночи.',
            'A night sky cut into a heart — for the person you love. Choose the night you met: the stars inside the heart are that night\'s.',
        ],
        'burc-astronimiya-heard-black-bg' => [
            'Eyni ürək, bu dəfə tünd fon üzərində — daha dramatik, axşam işığında daha yaxşı görünür. Tarix, saat və yer sizdən, ulduzlar səmadan.',
            'То же сердце, но на тёмном фоне — драматичнее и лучше смотрится при вечернем свете. Дата, время и место от вас, звёзды — с неба.',
            'The same heart, this time on a dark page — more dramatic, and better in evening light. The date, the hour and the place are yours; the stars are the sky\'s.',
        ],
        'burc-astronimiya-red' => [
            'Al rəngli səma — uzaqdan görünən, yaddaqalan dizayn. Ad günü və ildönümü üçün: o gecənin səması, adı və koordinatları ilə birlikdə.',
            'Алое небо — заметный дизайн, который запоминается. Для дня рождения и годовщины: небо той ночи вместе с именем и координатами.',
            'A crimson sky — a design that is seen across a room and remembered. For a birthday or an anniversary: that night\'s sky with the name and the coordinates.',
        ],
        'burc-astronimiya-stars' => [
            'Kosmos: bənövşəyi işıq, Süd Yolu və minlərlə ulduz, aşağıda ağa keçir. Künclərə qədər dolu, poster kimi görünən dizayn.',
            'Космос: фиолетовое свечение, Млечный Путь и тысячи звёзд, внизу переходящие в белое. Залито до углов — выглядит как постер.',
            'Cosmos: a violet glow, the Milky Way and thousands of stars fading into white below. Filled to the corners, it reads like a poster.',
        ],
        'burc-astronimiya-white-bg' => [
            'Ağ kağız üzərində qara səma dairəsi — ən sadə və ən təmiz variant. Hər interyerə, hər hədiyyəyə yaraşır.',
            'Чёрный круг неба на белой бумаге — самый простой и самый чистый вариант. Подходит к любому интерьеру и любому подарку.',
            'A black disc of sky on white paper — the plainest and cleanest of them. It suits any room and any gift.',
        ],
    ];

    /** The line every one of them ends with. */
    private const TAIL = [
        'Tarix, saat və yer sifariş səhifəsində seçilir; ad, koordinatlar və tarix avtomatik yazılır.',
        'Дата, время и место выбираются на странице заказа; имя, координаты и дата подставляются автоматически.',
        'The date, the hour and the place are chosen on the order page; the name, the coordinates and the date write themselves.',
    ];

    public function up(): void
    {
        if (DB::table('products')->count() === 0) {
            return;                         // a fresh install, and every test database
        }

        foreach (self::WORDS as $slug => [$az, $ru, $en]) {
            $product = Product::where('slug', $slug)->first();
            if (! $product || filled($product->description)) {
                continue;                   // not here, or the owner has written his own
            }

            $product->description = $az . ' ' . self::TAIL[0];
            $this->translate($product, 'ru', $ru . ' ' . self::TAIL[1]);
            $this->translate($product, 'en', $en . ' ' . self::TAIL[2]);
            $product->save();
        }

        // The shelf itself was put up in the admin, so it has no Russian or
        // English name yet and reads "Bürc" on both of those pages.
        $shelf = ProductCategory::where('slug', 'zodiac')->first();
        if ($shelf) {
            foreach (['ru' => 'Звёздные карты', 'en' => 'Star maps'] as $locale => $name) {
                if (blank(data_get($shelf->i18n, $locale . '.name'))) {
                    $shelf->setTranslations($locale, ['name' => $name] + $shelf->translationsFor($locale));
                }
            }
            $shelf->save();
        }
    }

    public function down(): void
    {
        // The words stay: taking them away would leave the cards blank again.
    }

    private function translate(Product $product, string $locale, string $text): void
    {
        if (blank(data_get($product->i18n, $locale . '.description'))) {
            $product->setTranslations($locale, ['description' => $text] + $product->translationsFor($locale));
        }
    }
};
