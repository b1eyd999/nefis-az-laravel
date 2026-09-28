<?php

namespace Tests\Feature;

use App\Filament\Pages\CorporateSettings;
use App\Models\CorporateRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\CorporatePage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The page that offers companies the small chocolate with their own logo:
 * what it says, what it lets them try on, and the request it takes. Nothing
 * on it is priced — that conversation happens after.
 */
class CorporateTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ADMIN, 'is_admin' => true]);
    }

    public function test_the_page_opens_and_says_what_the_shop_offers(): void
    {
        $this->get(route('corporate.index'))->assertOk()
            ->assertSee(CorporatePage::DEFAULTS['title'])
            ->assertSee(CorporatePage::DEFAULTS['min_qty_note'])
            // The two faces are drawn in the visitor's own browser.
            ->assertSee('js/corporate-box.js', false)
            ->assertSee('co-try-front', false);
    }

    public function test_the_shop_is_linked_to_it_from_the_menu(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('corporate.index'), false);
    }

    public function test_the_owners_words_replace_the_written_ones_and_the_rest_stay(): void
    {
        Setting::put(Setting::CORPORATE, json_encode([
            'title' => 'Şirkətinizin şokoladı',
            'min_qty' => '250',
            'whom' => [['icon' => '🏦', 'title' => 'Bank', 'text' => 'Müştəri gözləyərkən.']],
        ], JSON_UNESCAPED_UNICODE));

        $page = $this->get(route('corporate.index'))->assertOk();

        $page->assertSee('Şirkətinizin şokoladı')
            ->assertSee('250')
            ->assertSee('Bank')
            // A list he rewrote replaces the written one entirely.
            ->assertDontSee('Gözəllik salonu')
            // A line he never touched still reads as it was written.
            ->assertSee(CorporatePage::DEFAULTS['back_text']);

        $this->assertSame(250, CorporatePage::minimum());
    }

    /** Emptying a list is a decision, not a mistake: the built-in rows do not creep back. */
    public function test_a_list_the_owner_emptied_stays_empty(): void
    {
        Setting::put(Setting::CORPORATE, json_encode(['whom' => [], 'perks' => []], JSON_UNESCAPED_UNICODE));

        $this->get(route('corporate.index'))->assertOk()
            ->assertDontSee(CorporatePage::WHOM[0]['title'])
            ->assertDontSee(CorporatePage::DEFAULTS['whom_title'])
            ->assertDontSee(CorporatePage::DEFAULTS['perks_title'])
            // The colours he never touched are still the page's own.
            ->assertSee(CorporatePage::COLORS[0]['name']);
    }

    public function test_a_half_filled_row_does_not_leave_a_blank_card(): void
    {
        Setting::put(Setting::CORPORATE, json_encode([
            'perks' => [['title' => 'Real üstünlük', 'text' => 'Bir cümlə.'], ['title' => '  ', 'text' => 'Adsız qalıb']],
        ], JSON_UNESCAPED_UNICODE));

        $this->get(route('corporate.index'))->assertOk()
            ->assertSee('Real üstünlük')
            ->assertDontSee('Adsız qalıb');
    }

    public function test_a_company_asks_and_the_shop_gets_everything_it_needs_to_answer(): void
    {
        Storage::fake('public');

        $this->post(route('corporate.store'), [
            'company' => 'Güvən Optika',
            'person' => 'Elçin',
            'phone' => '+994 55 123 45 67',
            'email' => 'info@guven.az',
            'quantity' => 500,
            'logo' => UploadedFile::fake()->image('logo.png', 600, 200),
            'box_color' => '#1B3A6B',
            'slogan' => 'Həyata güvənlə baxın',
            'qr_target' => 'instagram.com/guven',
            'note' => 'Yeni filialın açılışına',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $asked = CorporateRequest::sole();
        $this->assertSame('Güvən Optika', $asked->company);
        $this->assertSame(500, $asked->quantity);
        $this->assertSame('#1B3A6B', $asked->box_color);
        $this->assertSame('new', $asked->status);
        $this->assertNotNull($asked->logo);
        Storage::disk('public')->assertExists($asked->logo);
        // The shop writes back on the number they left.
        $this->assertSame('https://wa.me/994551234567', $asked->whatsapp());
    }

    public function test_fewer_than_the_minimum_is_refused_with_the_number_said_out_loud(): void
    {
        Setting::put(Setting::CORPORATE, json_encode(['min_qty' => '100'], JSON_UNESCAPED_UNICODE));

        $this->post(route('corporate.store'), [
            'company' => 'Kiçik kafe', 'phone' => '+994 55 111 22 33', 'quantity' => 10,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(0, CorporateRequest::count());

        // And the same form with enough of them goes through.
        $this->post(route('corporate.store'), [
            'company' => 'Kiçik kafe', 'phone' => '+994 55 111 22 33', 'quantity' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, CorporateRequest::count());
    }

    public function test_the_logo_goes_when_the_request_does(): void
    {
        Storage::fake('public');

        $this->post(route('corporate.store'), [
            'company' => 'Kafe', 'phone' => '+994 55 111 22 33', 'quantity' => 200,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHasNoErrors();

        $asked = CorporateRequest::sole();
        $path = $asked->logo;
        $asked->delete();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_box_is_shown_standing_where_it_will_stand(): void
    {
        $page = $this->get(route('corporate.index'))->assertOk();

        // The photographs are real; only the printed face is drawn, so the
        // page has to carry both the picture and the quad to draw it on.
        $page->assertSee('co-scene-canvas', false)
            ->assertSee('js/scene-render.js', false);

        foreach (CorporatePage::SCENES as $scene) {
            $page->assertSee($scene['image'], false);
            $this->assertFileExists(public_path($scene['image']), $scene['image'] . ' is missing');

            $this->assertCount(4, $scene['corners'], 'a quad has four corners');
            foreach ($scene['corners'] as $corner) {
                $this->assertCount(2, $corner);
                foreach ($corner as $part) {
                    // Fractions of the frame, so the same numbers hold at any
                    // size the picture is served at.
                    $this->assertGreaterThan(0, $part);
                    $this->assertLessThan(1, $part);
                }
            }
        }
    }

    public function test_the_owner_can_switch_the_whole_thing_off(): void
    {
        \App\Models\Setting::put(\App\Models\Setting::CORPORATE_ENABLED, '0');

        // Not merely hidden: the page is gone, so an old link or a search
        // result cannot take a company to something the shop has stopped
        // making — and the request cannot be posted either.
        $this->get(route('corporate.index'))->assertNotFound();
        $this->post(route('corporate.store'), [
            'company' => 'Kafe', 'phone' => '+994 55 111 22 33', 'quantity' => 500,
        ])->assertNotFound();
        $this->assertSame(0, CorporateRequest::count());

        $this->get(route('home'))->assertOk()->assertDontSee(route('corporate.index'), false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('sirketler-ucun', false);

        \App\Models\Setting::put(\App\Models\Setting::CORPORATE_ENABLED, '1');

        $this->get(route('corporate.index'))->assertOk();
        $this->get(route('home'))->assertSee(route('corporate.index'), false);
        $this->get('/sitemap.xml')->assertSee('sirketler-ucun', false);
    }

    public function test_the_switch_is_on_the_panels_own_page(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CorporateSettings::class)
            ->assertFormSet(['enabled' => true])
            ->fillForm(['enabled' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(CorporatePage::enabled());
        $this->get(route('corporate.index'))->assertNotFound();

        // And the words he wrote are still there when he turns it back on.
        Livewire::test(CorporateSettings::class)->fillForm(['enabled' => true])->call('save');
        $this->assertTrue(CorporatePage::enabled());
        $this->get(route('corporate.index'))->assertOk()->assertSee(CorporatePage::DEFAULTS['title']);
    }

    public function test_the_owner_rewrites_the_page_in_the_panel(): void
    {
        $this->actingAs($this->owner());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CorporateSettings::class)
            ->assertFormSet(['title' => CorporatePage::DEFAULTS['title']])
            ->fillForm(['title' => 'Korporativ şokolad', 'min_qty' => '300'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Korporativ şokolad', CorporatePage::all()['title']);
        $this->assertSame(300, CorporatePage::minimum());
        $this->get(route('corporate.index'))->assertSee('Korporativ şokolad');
    }

    public function test_the_books_and_the_requests_are_the_owners_alone(): void
    {
        $manager = User::factory()->create(['role' => User::MANAGER, 'is_admin' => false]);

        $this->assertTrue(CorporateSettings::canAccess() === false || auth()->guest());

        $this->actingAs($manager);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertFalse(CorporateSettings::canAccess());
        $this->assertFalse(\App\Filament\Resources\CorporateRequestResource::canViewAny());

        $this->actingAs($this->owner());
        $this->assertTrue(CorporateSettings::canAccess());
        $this->assertTrue(\App\Filament\Resources\CorporateRequestResource::canViewAny());
    }
}
