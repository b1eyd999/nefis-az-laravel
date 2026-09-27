<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The shop speaks three languages: its own words come from the translation
 * files, and what the owner writes — a design's name, how it is delivered —
 * he may write again in Russian and English in the admin.
 */
class TranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');
    }

    private function box(): Product
    {
        $box = Product::create(['name' => 'Love Story', 'slug' => 'love-story', 'is_active' => true, 'price' => 4.90,
            'description' => 'Sevgililər üçün qutu', 'template_width' => 969, 'template_height' => 1895,
            'i18n' => ['ru' => ['name' => 'История любви', 'description' => 'Коробка для влюблённых'],
                'en' => ['name' => 'Love Story', 'description' => 'A box for the two of you']]]);
        $box->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
            'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        return $box;
    }

    public function test_the_site_speaks_the_language_of_the_address(): void
    {
        $this->box();

        // The catalogue names the designs…
        $this->get('/dizaynlar')->assertOk()->assertSee('Dizaynlar')->assertSee('Love Story');
        $this->get('/ru/dizaynlar')->assertOk()
            ->assertSee('Дизайны')                       // the shop's own words
            ->assertSee('История любви')                 // and what the owner wrote
            ->assertSee('<html lang="ru">', false);

        // …and the home page's cards carry what each one is.
        $this->get('/')->assertOk()->assertSee('Sevgililər üçün qutu');
        $this->get('/ru')->assertOk()
            ->assertSee('Коробка для влюблённых')
            ->assertSee('Как это работает');
        $this->get('/en')->assertOk()
            ->assertSee('A box for the two of you')
            ->assertSee('How it works')
            ->assertSee('<html lang="en">', false);
    }

    public function test_what_was_never_translated_stays_azerbaijani(): void
    {
        Product::create(['name' => 'Kinder', 'slug' => 'kinder', 'is_active' => true, 'price' => 5,
            'description' => 'Uşaqlar üçün', 'template_width' => 969, 'template_height' => 1895])
            ->layers()->create(['name' => 'BG', 'image' => 'boxes/bg.webp', 'x' => 0, 'y' => 0, 'width' => 969,
                'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'above', 'sort_order' => 0]);

        // A design with no Russian words of its own is still shown, as written.
        $this->get('/ru/dizaynlar')->assertOk()->assertSee('Kinder');
        $this->get('/ru')->assertOk()->assertSee('Uşaqlar üçün');
    }

    public function test_the_owner_writes_the_other_languages_in_the_admin(): void
    {
        $door = DeliveryMethod::where('type', DeliveryMethod::DOOR)->firstOrFail();
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Resources\DeliveryMethodResource\Pages\EditDeliveryMethod::class,
            ['record' => $door->getRouteKey()])
            ->fillForm(['i18n' => ['ru' => ['name' => 'Курьером до двери'], 'en' => ['name' => 'To your door']]])
            ->call('save')
            ->assertHasNoFormErrors();

        $door->refresh();
        $this->assertSame('Курьером до двери', $door->i18n['ru']['name']);

        app()->setLocale('ru');
        $this->assertSame('Курьером до двери', $door->tr('name'));
        app()->setLocale('en');
        $this->assertSame('To your door', $door->tr('name'));
        app()->setLocale('az');
        $this->assertSame($door->name, $door->tr('name'));
    }

    public function test_the_shipped_design_translations_only_fill_what_is_empty(): void
    {
        $all = require database_path('data/design-translations.php');
        $this->assertGreaterThan(20, count($all), 'the catalogue is translated, not a sample of it');

        // Every row must carry both languages, or a visitor gets Azerbaijani
        // on a page that promised him his own.
        foreach ($all as $slug => $langs) {
            $this->assertArrayHasKey('ru', $langs, $slug);
            $this->assertArrayHasKey('en', $langs, $slug);
            $this->assertNotEmpty($langs['ru']['description'] ?? null, $slug);
            $this->assertNotEmpty($langs['en']['description'] ?? null, $slug);
        }

        $slug = array_key_first($all);
        $design = \App\Models\Product::create(['name' => 'Test', 'slug' => $slug, 'is_active' => true, 'price' => 4.90]);
        // The owner got there first in Russian; his words must survive.
        $design->setTranslations('ru', ['description' => 'Моё описание']);
        $design->saveQuietly();

        // Run the migration's own code: the suite has already applied it once
        // on an empty catalogue, so artisan would find nothing left to do.
        (require database_path('migrations/2026_09_27_000300_the_designs_speak_russian_and_english.php'))->up();

        $design->refresh();
        $this->assertSame('Моё описание', $design->translationsFor('ru')['description']);
        $this->assertSame($all[$slug]['en']['description'], $design->translationsFor('en')['description']);
    }

    public function test_a_design_is_read_in_the_visitors_language_everywhere_it_appears(): void
    {
        $design = \App\Models\Product::create(['name' => 'Alpen gold', 'slug' => 'alpen-gold',
            'description' => 'Azərbaycanca təsvir', 'is_active' => true, 'price' => 4.90,
            'template_width' => 969, 'template_height' => 1895]);
        $design->layers()->create(['name' => 'Qutu', 'image' => 'boxes/art.webp', 'x' => 0, 'y' => 0,
            'width' => 969, 'height' => 1895, 'rotation' => 0, 'opacity' => 100, 'placement' => 'below', 'sort_order' => 0]);
        $design->setTranslations('ru', ['name' => 'Русское имя', 'description' => 'Русское описание']);
        $design->saveQuietly();

        // The catalogue and the design's own page printed the Azerbaijani
        // columns straight out, so a translated design still read Azerbaijani
        // to a Russian visitor — the translation was in the database all along.
        $this->get('/ru/dizaynlar')->assertOk()->assertSee('Русское имя')->assertDontSee('Alpen gold');
        $this->get('/ru/products/alpen-gold/customize')->assertOk()
            ->assertSee('Русское имя')
            ->assertSee('Русское описание')
            ->assertDontSee('Azərbaycanca təsvir');

        // And Azerbaijani still reads Azerbaijani.
        $this->get('/products/alpen-gold/customize')->assertOk()->assertSee('Alpen gold');
    }
}
