<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The words above the fields a customer fills in.
 *
 * Two things can go wrong with them. One: a design keeps the caption the
 * editor gave it — "Mətn 1" — and asks for "Text 1" where its neighbours ask
 * for a name. Two: the owner writes a caption in Azerbaijani and nobody adds
 * the Russian and English, so those pages print the Azerbaijani raw; the
 * caption goes through `__()`, so the translation files are where that is
 * answered.
 */
class SlotLabelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every Azerbaijani caption the owner has typed into a design, with what
     * the other two languages show instead. Read off the live site on
     * 2026-10-04, when fifty slots across sixty designs printed the
     * Azerbaijani on the Russian and English pages.
     */
    private const CAPTIONS = [
        'Hər şeyin başladığı yer' => ['Место, где всё началось', 'The place where it all began'],
        'Məkanın adı' => ['Название места', 'Place name'],
        'Əlavə söz & lokasiyanın məlumatları' => ['Дополнительные слова и данные локации', 'Extra words and location details'],
        'Hədiyyə verəcəyiniz şəxsin adı' => ['Имя того, кому дарите', 'Name of the person you are giving it to'],
        'Istədiyiniz mətini qeyd edin' => ['Напишите любой текст', 'Write any text you like'],
        'Hədiyyə vermək istədiyiniz şəxsin adını qeyd edin' => ['Укажите имя того, кому дарите', 'Write the name of the person you are giving it to'],
        'Yazmaq istədiyiniz mətin' => ['Ваш текст', 'Your text'],
        'Qeyd etmək istədiyiniz mətini yazın' => ['Напишите свой текст', 'Write your text'],
        'Model' => ['Модель', 'Model'],
        'Hədiyyə vermək istədiyiniz şəxsin adı' => ['Имя того, кому дарите', 'Name of the person you are giving it to'],
        'İstədiyiniz şəkili yükləyin' => ['Загрузите любое фото', 'Upload any photo you like'],
        'Qeyd etmək istədiyiniz cümlə' => ['Ваша фраза', 'Your words'],
    ];

    /**
     * The designs that ask for more than one thing. Two captions standing on
     * the same page must stay two captions in every language: the owner wrote
     * the shop's three ways of saying "the name of the person it is for"
     * on separate designs, and those read alike on purpose — but a design that
     * asks for a name *and* a line of text must not ask for the same thing
     * twice once it is translated.
     */
    private const TOGETHER = [
        'burc-astronimiya' => ['Məkanın adı', 'Əlavə söz & lokasiyanın məlumatları'],
        'alyonka-qar-qiz' => ['Qeyd etmək istədiyiniz mətini yazın', 'Hədiyyə vermək istədiyiniz şəxsin adını qeyd edin'],
        'cici-bebe-boz' => ['Hədiyyə verəcəyiniz şəxsin adı', 'Yazmaq istədiyiniz mətin'],
        'zencirli-box' => ['Hədiyyə verəcəyiniz şəxsin adı', 'Qeyd etmək istədiyiniz cümlə'],
        'pampers' => ['Hədiyyə vermək istədiyiniz şəxsin adını qeyd edin', 'Istədiyiniz mətini qeyd edin', 'İstədiyiniz şəkili yükləyin'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
    }

    private function box(string $slug, string $label): Product
    {
        $box = Product::create(['name' => 'Love is', 'slug' => $slug, 'is_active' => true, 'price' => 4.90,
            'description' => 'Qutu', 'template_width' => 969, 'template_height' => 1895]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);
        $box->textSlots()->create(['label' => $label, 'x' => 100, 'y' => 1500, 'sort_order' => 0]);

        return $box;
    }

    private function migrate(): void
    {
        (require database_path('migrations/2026_10_04_000300_the_blue_love_is_box_names_its_field.php'))->up();
    }

    public function test_the_blue_box_stops_asking_for_text_1(): void
    {
        $box = $this->box('love-is-blue2', 'Mətn 1');

        $this->migrate();

        $this->assertSame('Hədiyyə verəcəyiniz şəxsin adı', $box->textSlots()->first()->label,
            'it now asks for the same thing as love-is-mix, love-is-orange2 and love-is-red3');
    }

    public function test_a_caption_the_owner_wrote_himself_is_left_alone(): void
    {
        $box = $this->box('love-is-blue2', 'Sevgilinizin adı');

        $this->migrate();

        $this->assertSame('Sevgilinizin adı', $box->textSlots()->first()->label);
    }

    public function test_the_captions_of_the_whole_catalogue_are_translated(): void
    {
        foreach (self::CAPTIONS as $az => [$ru, $en]) {
            $this->assertSame($ru, __($az, [], 'ru'), "Russian is missing for: {$az}");
            $this->assertSame($en, __($az, [], 'en'), "English is missing for: {$az}");
        }
    }

    public function test_two_captions_on_one_page_stay_two_captions(): void
    {
        foreach (self::TOGETHER as $slug => $captions) {
            foreach (['ru', 'en'] as $locale) {
                $said = array_map(fn ($az) => __($az, [], $locale), $captions);

                $this->assertSame($said, array_values(array_unique($said)),
                    "two fields of {$slug} read the same in {$locale}");
            }
        }
    }

    public function test_the_page_shows_the_caption_in_the_readers_language(): void
    {
        $this->box('love-is-blue2', 'Mətn 1');
        $this->migrate();

        $az = 'Hədiyyə verəcəyiniz şəxsin adı';

        $this->get('/products/love-is-blue2/customize')->assertOk()->assertSee($az);
        $this->get('/ru/products/love-is-blue2/customize')->assertOk()
            ->assertSee(__($az, [], 'ru'))->assertDontSee($az);
        $this->get('/en/products/love-is-blue2/customize')->assertOk()
            ->assertSee(__($az, [], 'en'))->assertDontSee($az);
    }
}
