<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\DesignCopier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The location boxes get their insides, and words of their own.
 *
 * Eleven went up in one evening and only the first was drawn: the other ten
 * had a cover in the catalogue and nothing behind it, so the shop told every
 * customer who opened them that the design was not ready. They differ from
 * the finished one in two settings — the shape of the window and its colours
 * — so the drawing is copied and only those two are changed.
 *
 * Nothing already drawn is touched: a box with anything at all inside it is
 * left alone, and so is any description the owner has written himself.
 */
return new class extends Migration
{
    private const SOURCE = 'lokasiya';

    /** slug => what the box is named after: the window's shape and colours. */
    private const FAMILY = [
        'lokasiya-invert' => ['map_style' => 'paper'],
        'lokasiya-green' => ['map_style' => 'sea'],
        'lokasiya-rgb' => ['map_style' => 'colour'],
        'lokasiya-daireli' => ['shape' => 'ellipse', 'map_style' => 'ink'],
        'lokasiya-daireli-invert' => ['shape' => 'ellipse', 'map_style' => 'paper'],
        'lokasiya-daireli-green' => ['shape' => 'ellipse', 'map_style' => 'sea'],
        'lokasiya-home' => ['shape' => 'home', 'map_style' => 'ink'],
        'lokasiya-home-invert' => ['shape' => 'home', 'map_style' => 'paper'],
        'lokasiya-home-green' => ['shape' => 'home', 'map_style' => 'sea'],
        'lokasiya-home-rgb' => ['shape' => 'home', 'map_style' => 'colour'],
    ];

    /** slug => [Azerbaijani, Russian, English] */
    private const WORDS = [
        'lokasiya' => [
            'Qara fon, ağ küçələr — sizin yerinizin xəritəsi. Metro, ev, ilk görüş yeri: ünvanı yazın, xəritəni barmağınızla tutub lazımi kadrı seçin.',
            'Чёрный фон, белые улицы — карта вашего места. Метро, дом, место первой встречи: напишите адрес и пальцем поймайте нужный кадр.',
            'A black ground and white streets — the map of your own place. A metro, a home, where you first met: type the address and catch the frame with your finger.',
        ],
        'lokasiya-invert' => [
            'Ağ kağız üzərində qara küçələr — işıqlı, sakit variant. Eyni xəritə, yalnız tərsinə: açıq rəngli otaqda daha yaxşı oturur.',
            'Чёрные улицы по белой бумаге — светлый, спокойный вариант. Та же карта, только наоборот: в светлой комнате смотрится лучше.',
            'Black streets on white paper — the light, quiet version. The same map turned inside out; it sits better in a bright room.',
        ],
        'lokasiya-green' => [
            'Dəniz mavisi: suyu və parkları ayırd edən yumşaq xəritə. Bakı kimi sahil şəhərləri üçün — körfəz özü naxış olur.',
            'Морская синева: мягкая карта, на которой видно воду и парки. Для приморских городов вроде Баку — залив сам становится узором.',
            'Sea blue: a softer map where the water and the parks show. Made for a coastal city like Baku, where the bay becomes the pattern itself.',
        ],
        'lokasiya-rgb' => [
            'Adi rəngli xəritə — yollar, parklar, sular öz rənglərində. Uşaq otağı və şən hədiyyələr üçün ən canlısı.',
            'Обычная цветная карта — дороги, парки и вода в своих цветах. Самый живой вариант: для детской и весёлых подарков.',
            'The ordinary coloured map — roads, parks and water in their own colours. The liveliest of them, for a child\'s room or a cheerful present.',
        ],
        'lokasiya-daireli' => [
            'Küçələr dairənin içində, qara fonda. Dairə şəhərin bir parçasını medal kimi kəsib götürür — altında ad və koordinatlar.',
            'Улицы внутри круга, по чёрному. Круг вырезает кусок города, как медальон, — под ним название и координаты.',
            'Streets inside a circle on black. The circle cuts a piece of the city out like a medallion, with the name and the coordinates beneath.',
        ],
        'lokasiya-daireli-invert' => [
            'Dairə, ağ fonda qara küçələrlə. Toy və ildönümü üçün: sadə, işıqlı, çərçivəyə qoymağa hazır görünüş.',
            'Круг с чёрными улицами по белому. Для свадьбы и годовщины: простой, светлый вид, будто уже в рамке.',
            'A circle of black streets on white. For a wedding or an anniversary: plain, light, and already looking framed.',
        ],
        'lokasiya-daireli-green' => [
            'Mavi dairə: su və yaşıllıq göründüyü üçün sahil və park yerləri bu variantda ən gözəl çıxır.',
            'Синий круг: вода и зелень видны, поэтому набережные и парки в этом варианте получаются красивее всего.',
            'A blue circle: the water and the greenery show, so a waterfront or a park comes out best in this one.',
        ],
        'lokasiya-home' => [
            'Xəritə ev şəklindədir — qara fon, ağ küçələr. Yeni evə köçənlərə: damın altında öz məhəllənizin küçələri.',
            'Карта в форме дома — чёрный фон, белые улицы. Тем, кто переехал: под крышей улицы собственного квартала.',
            'The map in the shape of a house — black ground, white streets. For someone who has just moved: their own streets under a roof.',
        ],
        'lokasiya-home-invert' => [
            'Ev forması, ağ fonda qara küçələr. Divarda asmaq üçün ən sakit variant — yazılar aydın oxunur.',
            'Дом, чёрные улицы по белому. Самый спокойный вариант для стены — надписи читаются отчётливо.',
            'The house shape with black streets on white. The quietest one to hang on a wall, and the lettering reads cleanly.',
        ],
        'lokasiya-home-green' => [
            'Mavi ev: suyun yanındakı evlər üçün. Körfəz, çay, göl — hamısı damın altına düşür.',
            'Синий дом: для тех, кто живёт у воды. Залив, река, озеро — всё помещается под крышу.',
            'A blue house, for a home by the water. A bay, a river, a lake — all of it fits under the roof.',
        ],
        'lokasiya-home-rgb' => [
            'Rəngli ev — şəhər öz rənglərində, dam formasının içində. Uşaqlar üçün ən başa düşüləni.',
            'Цветной дом — город в своих красках внутри формы крыши. Детям понятнее всего именно этот.',
            'A coloured house — the city in its own colours inside the roof shape. The one children understand at once.',
        ],
    ];

    /** Said at the end of every one of them. */
    private const TAIL = [
        'Xəritə məlumatları: © OpenStreetMap.',
        'Данные карты: © OpenStreetMap.',
        'Map data: © OpenStreetMap.',
    ];

    public function up(): void
    {
        if (DB::table('products')->count() === 0) {
            return;                         // a fresh install, and every test database
        }

        $this->spread();
        $this->describe();
        $this->nameTheShelf();
    }

    /** The finished box's drawing, laid onto the ones that have none. */
    private function spread(): void
    {
        $source = Product::where('slug', self::SOURCE)->first();

        if (! $source || ! $source->photoSlots()->where('fill', 'map')->exists()) {
            echo "  location: no finished '" . self::SOURCE . "' to copy from, nothing spread\n";

            return;
        }

        foreach (self::FAMILY as $slug => $window) {
            $box = Product::where('slug', $slug)->first();
            if (! $box) {
                continue;
            }

            /* Anything at all inside it means the owner has been working on
               it, and his work is never overwritten. */
            if (! $box->isBlank()) {
                echo "  location: {$slug} already has a design, left alone\n";

                continue;
            }

            $made = DesignCopier::copy($source, $box, $window);

            /* A box switched off for being empty can stand on the shelf again. */
            if (! $box->fresh()->isBlank()) {
                $box->forceFill(['is_active' => true])->save();
            }

            echo "  location: {$slug} filled in — " . json_encode($made) . "\n";
        }
    }

    /** What each one looks like, in all three languages. */
    private function describe(): void
    {
        foreach (self::WORDS as $slug => [$az, $ru, $en]) {
            $box = Product::where('slug', $slug)->first();
            if (! $box || filled($box->description)) {
                continue;                   // not here, or the owner wrote his own
            }

            $box->description = $az . ' ' . self::TAIL[0];
            $this->translate($box, 'ru', $ru . ' ' . self::TAIL[1]);
            $this->translate($box, 'en', $en . ' ' . self::TAIL[2]);
            $box->save();
        }
    }

    /** The shelf was put up in the admin, so it reads Azerbaijani on /ru and /en. */
    private function nameTheShelf(): void
    {
        $shelf = ProductCategory::where('slug', 'lokasiya')->orWhere('name', 'Lokasiya')->first();
        if (! $shelf) {
            return;
        }

        foreach (['ru' => 'Карты мест', 'en' => 'Place maps'] as $locale => $name) {
            if (blank(data_get($shelf->i18n, $locale . '.name'))) {
                $shelf->setTranslations($locale, ['name' => $name] + $shelf->translationsFor($locale));
            }
        }
        $shelf->save();
    }

    private function translate(Product $product, string $locale, string $text): void
    {
        if (blank(data_get($product->i18n, $locale . '.description'))) {
            $product->setTranslations($locale, ['description' => $text] + $product->translationsFor($locale));
        }
    }

    public function down(): void
    {
        // Left as it is: the owner may have edited these boxes since, and a
        // design is not something to throw away on a rollback.
    }
};
