<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Support\Info;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The three pages that answer a visitor before he buys.
 *
 * All three were sections of the front page, reachable by «#how» and «#faq»
 * alone: nothing to send anybody, nothing a search engine could land on, and
 * from any other page the footer's own links went to the front page and left
 * the visitor to find the section himself.
 */
class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_three_pages_open(): void
    {
        $this->get('/nece-isleyir')->assertOk()->assertSee(__('Üç Addımda Fərdi Hədiyyə'));
        $this->get('/suallar')->assertOk()->assertSee(__('Necə sifariş verə bilərəm?'));
        $this->get('/elaqe')->assertOk()->assertSee(__('Bizimlə əlaqə'));
    }

    /** And in the other two languages, at their own addresses. */
    public function test_they_open_in_every_language(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');

        $this->get('/ru/suallar')->assertOk()->assertSee('Частые вопросы');
        $this->get('/en/nece-isleyir')->assertOk()->assertSee('A personal gift in three steps');
        $this->get('/ru/elaqe')->assertOk()->assertSee('Связаться с нами');
    }

    /** The questions are given to search engines as well as shown. */
    public function test_the_questions_are_given_to_search_engines(): void
    {
        $this->get('/suallar')->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Question"', false);
    }

    /** And the three pages are in the sitemap, once per language. */
    public function test_they_are_in_the_sitemap(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/nece-isleyir', $xml);
        $this->assertStringContainsString('/suallar', $xml);
        $this->assertStringContainsString('/elaqe', $xml);
    }

    /** The footer points at the pages, not at anchors on the front page. */
    public function test_the_footer_points_at_the_pages(): void
    {
        $home = $this->get('/')->assertOk();

        $home->assertSee(url('/suallar'), false);
        $home->assertSee(url('/nece-isleyir'), false);
        $home->assertSee(url('/elaqe'), false);
        $home->assertDontSee('href="http://localhost#faq"', false);
    }

    /** One list, edited once, shown on the front page and on its own page. */
    public function test_the_owner_edits_one_list_for_both(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\InfoSettings::class)
            ->fillForm([
                'faq_title' => 'Nə soruşursunuz',
                'faq' => [
                    ['q' => 'Qutuya nə sığır?', 'a' => 'Bir plitka şokolad və bir polaroid.'],
                ],
                'steps' => [
                    ['title' => 'Seç', 'text' => 'Dizaynı seç.'],
                ],
            ])
            ->call('save');

        $this->assertSame('Nə soruşursunuz', Info::text('faq_title'));
        $this->assertCount(1, Info::faq());

        $this->get('/suallar')->assertOk()
            ->assertSee('Nə soruşursunuz')
            ->assertSee('Qutuya nə sığır?')
            ->assertDontSee(__('Necə sifariş verə bilərəm?'));

        $this->get('/')->assertOk()->assertSee('Qutuya nə sığır?');
        $this->get('/nece-isleyir')->assertOk()->assertSee('Dizaynı seç.');
    }

    /** A half-filled row in the admin never leaves a blank card on the page. */
    public function test_an_empty_row_is_dropped(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\InfoSettings::class)
            ->fillForm(['faq' => [
                ['q' => 'Həqiqi sual?', 'a' => 'Həqiqi cavab.'],
                ['q' => 'Cavabı olmayan', 'a' => ''],
                ['q' => '', 'a' => 'Sualı olmayan'],
            ]])
            ->call('save');

        $this->assertCount(1, Info::faq());
    }

    public function test_a_message_is_written_down_and_shown_to_the_owner(): void
    {
        $this->post('/elaqe', [
            'name' => 'Aygün',
            'phone' => '055 555 55 55',
            'message' => 'Sifarişim nə vaxt hazır olacaq?',
            'about' => 'sifariş #12',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $message = ContactMessage::firstOrFail();
        $this->assertSame('Aygün', $message->name);
        $this->assertSame('sifariş #12', $message->about);
        $this->assertNull($message->answered_at, 'it waits for an answer');
        $this->assertSame('az', $message->locale);

        // The owner sees it in his own list.
        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/contact-messages')->assertOk()->assertSee('Aygün');
    }

    public function test_an_empty_message_is_refused(): void
    {
        $this->from('/elaqe')->post('/elaqe', ['name' => '', 'phone' => '', 'message' => ''])
            ->assertSessionHasErrors(['name', 'phone', 'message']);

        $this->assertSame(0, ContactMessage::count());
    }

    /** Ticking one off takes it out of the queue, and says who did it. */
    public function test_the_owner_ticks_a_message_off(): void
    {
        $message = ContactMessage::create(['name' => 'Aygün', 'phone' => '+994 55 555 55 55',
            'message' => 'Salam']);
        $admin = User::factory()->create(['role' => User::ADMIN]);

        $message->forceFill(['answered_at' => now(), 'answered_by' => $admin->id])->save();

        $this->assertTrue($message->fresh()->isAnswered());
        $this->assertSame($admin->id, $message->fresh()->answerer->id);
        $this->assertSame(0, ContactMessage::waiting()->count());
    }

    /** Only the owner reads them. */
    public function test_a_manager_does_not_reach_the_messages(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/contact-messages')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::MANAGER]))
            ->get('/admin/info-pages')->assertForbidden();
    }
}
