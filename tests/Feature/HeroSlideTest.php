<?php

namespace Tests\Feature;

use App\Filament\Resources\HeroSlideResource\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlideResource\Pages\ListHeroSlides;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class HeroSlideTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_banner_starts_as_it_was_one_slide_and_no_slider(): void
    {
        $this->assertSame(1, HeroSlide::count(), 'the original wording became the first slide');

        $this->get(route('home'))->assertOk()
            ->assertSee('Bir Xatirəyə Dönsün.')
            ->assertSee('Premium Şokolad')
            ->assertSee('Fərdi Hədiyyə')
            ->assertDontSee('data-go=', false);
    }

    public function test_the_owner_writes_a_second_slide_and_they_turn(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get('/admin/hero-slides')->assertOk()->assertSee('Ana səhifə slaydları');

        Livewire::test(CreateHeroSlide::class)
            ->fillForm([
                'eyebrow' => 'Yeni kolleksiya', 'title' => "Qış Hədiyyələri\nArtıq Satışda", 'text' => 'Yeni il üçün.',
                'button1_label' => 'Qablaşdırmaya bax', 'button1_url' => '/qablasdirma',
                'badges' => ['Yeni dizaynlar'], 'image' => UploadedFile::fake()->image('slide.jpg', 800, 840),
                'image_fit' => 'contain', 'ribbon' => 'Yeni', 'sort_order' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Turning every 4 seconds.
        Livewire::test(ListHeroSlides::class)->callAction('slider', ['autoplay' => true, 'interval' => 4])->assertHasNoActionErrors();

        $html = $this->get(route('home'))->assertOk()
            ->assertSee('Qış Hədiyyələri<br />', false)
            ->assertSee('href="/qablasdirma"', false)
            ->assertSee('Yeni dizaynlar')
            ->assertSee('data-interval="4"', false)
            ->getContent();
        $this->assertSame(2, substr_count($html, 'data-go='));
        $this->assertStringContainsString('class="fit-contain"', $html);
        $this->assertSame(1, substr_count($html, '<h1'), 'one main heading on the page');

        // Turning by hand only.
        Setting::put(Setting::HERO_AUTOPLAY, false);
        $this->get(route('home'))->assertSee('data-autoplay="0"', false);
    }

    public function test_links_are_the_sites_pages_or_real_sites_only(): void
    {
        $this->assertSame('/dizaynlar', HeroSlide::href('/dizaynlar'));
        $this->assertSame('#collections', HeroSlide::href('#collections'));
        $this->assertSame('https://instagram.com/nefis.az', HeroSlide::href('https://instagram.com/nefis.az'));
        $this->assertNull(HeroSlide::href('javascript:alert(1)'));
        $this->assertNull(HeroSlide::href('dizaynlar'));

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(CreateHeroSlide::class)
            ->fillForm(['title' => 'Test', 'button1_label' => 'Bax', 'button1_url' => 'javascript:alert(1)', 'image_fit' => 'cover'])
            ->call('create')
            ->assertHasFormErrors(['button1_url']);
    }

    public function test_hidden_slides_stay_off_and_without_any_the_designs_come_first(): void
    {
        HeroSlide::query()->update(['is_active' => false]);
        // No banner at all: the page opens with the designs, and their
        // heading becomes the page's own.
        $html = $this->get(route('home'))->assertOk()
            ->assertDontSee('id="hero"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'), 'one main heading on the page');
        $this->assertStringContainsString('Hər Zövqə Uyğun Dizaynlar', $html);

        HeroSlide::create(['title' => 'Görünməz', 'is_active' => false]);
        $this->get(route('home'))->assertDontSee('Görünməz');

        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))->get('/admin/hero-slides')->assertForbidden();
    }
}
