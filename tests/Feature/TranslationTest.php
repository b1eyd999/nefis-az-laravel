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
}
