<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Support\LivePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The live photo page speaks for the shop, so the owner writes its words
 * himself. What he leaves alone keeps the wording the page was written with,
 * and is still read in the customer's own language.
 */
class LivePageTextsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_opens_with_the_words_it_was_written_with(): void
    {
        $this->get(route('live.create'))->assertOk()
            ->assertSee(LivePage::DEFAULTS['title'])
            ->assertSee(LivePage::DEFAULTS['photo_hint']);
    }

    public function test_the_owner_rewrites_a_line_and_the_page_says_it(): void
    {
        Setting::put(Setting::LIVE_PAGE, json_encode([
            'title' => 'Sehrli şəkil',
            'button' => 'Sifariş et',
            'lede' => '',
        ], JSON_UNESCAPED_UNICODE));

        $this->get(route('live.create'))->assertOk()
            ->assertSee('Sehrli şəkil')
            ->assertSee('Sifariş et')
            // the line he left empty keeps its own wording
            ->assertSee(LivePage::DEFAULTS['lede']);
    }

    public function test_a_line_he_has_not_touched_is_still_translated(): void
    {
        Setting::put(Setting::SITE_LANGUAGES, 'az,ru,en');

        $this->get(route('ru.live.create'))->assertOk()
            ->assertSee(__(LivePage::DEFAULTS['lede'], [], 'ru'));
    }
}
