<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel is in the owner's language, Filament's own words included.
 *
 * Everything the shop writes into the panel was always Azerbaijani, but the
 * words Filament itself prints — the breadcrumb after a resource's name, the
 * search box, the sentence an empty table shows — come from its translation
 * files and follow the application's locale. The panel had no locale of its
 * own, so those followed APP_LOCALE: `en` here and on the server. The owner
 * saw a page reading «Rəylər · List», «Search» and «No rəylər», and asked
 * where those words were written down.
 */
class AdminSpeaksAzerbaijaniTest extends TestCase
{
    use RefreshDatabase;

    public function test_filaments_own_words_are_azerbaijani_in_the_panel(): void
    {
        config(['app.locale' => 'en']);

        $html = $this->actingAs(User::factory()->create(['role' => User::ADMIN]))
            ->get('/admin/reviews')->assertOk()->getContent();

        $this->assertStringContainsString('Axtar', $html, 'the search box');
        $this->assertStringNotContainsString('>Search<', $html);
        $this->assertStringNotContainsString('No rəylər', $html);

        /* Filament's own Azerbaijani file leaves one word in English — the
           breadcrumb over every list reads «Rəylər · List» — so the shop
           overrides it in lang/vendor/filament/az. */
        $this->assertStringContainsString('Siyahı', $html, 'the breadcrumb');
        $this->assertStringNotContainsString('>List<', $html);
    }

    public function test_the_shops_own_pages_still_follow_the_address(): void
    {
        // The panel's locale is the panel's alone: a shop page asked for in
        // Russian is still rendered in Russian.
        $this->get('/ru')->assertOk();
        $this->assertSame('ru', app()->getLocale());

        $this->actingAs(User::factory()->create(['role' => User::ADMIN]))->get('/admin/reviews')->assertOk();
        $this->assertSame('az', app()->getLocale(), 'and the panel is in his own language');
    }
}
