<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\CorporatePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * What a company actually pays, by the number it orders.
 *
 * The page said «say artdıqca bir ədədin qiyməti aşağı düşür» and then named
 * no figure at all, so every enquiry began with somebody asking what it
 * costs. The steps are the owner's own and start empty — a price invented in
 * code would be a price on a live page.
 */
class CorporateLadderTest extends TestCase
{
    use RefreshDatabase;

    private function ladder(array $steps): void
    {
        Setting::put(Setting::CORPORATE, (string) json_encode(['ladder' => $steps], JSON_UNESCAPED_UNICODE));
    }

    public function test_nothing_is_shown_until_the_owner_names_his_prices(): void
    {
        $this->assertSame([], CorporatePage::ladder());
        $this->assertNull(CorporatePage::unitPriceFor(500));
        $this->assertNull(CorporatePage::totalFor(500));

        // The block is not there at all — not merely empty. (The class name
        // itself lives in the page's stylesheet either way, so what is
        // looked for is the markup only the block writes.)
        $this->get('/sirketler-ucun')->assertOk()
            ->assertDontSee('class="co-steps"', false)
            ->assertDontSee('id="co-quote"', false);
    }

    /**
     * The highest step the number reaches, and no higher.
     *
     * 250 against steps of 100 and 300 is priced at the 100 step: quoting
     * the 300 one would give away a discount nobody has earned.
     */
    public function test_the_step_a_number_reaches_is_the_one_it_pays(): void
    {
        $this->ladder([
            ['from' => 100, 'price' => 1.80],
            ['from' => 300, 'price' => 1.55],
            ['from' => 1000, 'price' => 1.30],
        ]);

        $this->assertNull(CorporatePage::unitPriceFor(99), 'below the first step there is no price');
        $this->assertSame(1.80, CorporatePage::unitPriceFor(100));
        $this->assertSame(1.80, CorporatePage::unitPriceFor(250));
        $this->assertSame(1.55, CorporatePage::unitPriceFor(300));
        $this->assertSame(1.55, CorporatePage::unitPriceFor(999));
        $this->assertSame(1.30, CorporatePage::unitPriceFor(5000));

        $this->assertSame(450.0, CorporatePage::totalFor(250));
        $this->assertSame(465.0, CorporatePage::totalFor(300));
    }

    /** Written in any order, read in order; nonsense rows dropped. */
    public function test_the_steps_are_put_in_order_and_cleaned(): void
    {
        $this->ladder([
            ['from' => 1000, 'price' => 1.30],
            ['from' => 100, 'price' => 1.80],
            ['from' => 0, 'price' => 5],          // no such number
            ['from' => 500, 'price' => 0],        // no such price
            ['from' => 100, 'price' => 1.70],     // the same step twice
            ['nonsense' => true],
        ]);

        $this->assertSame([
            ['from' => 100, 'price' => 1.70],
            ['from' => 1000, 'price' => 1.30],
        ], CorporatePage::ladder());
    }

    public function test_the_page_shows_the_steps_and_the_live_sum(): void
    {
        $this->ladder([
            ['from' => 100, 'price' => 1.80],
            ['from' => 300, 'price' => 1.55],
        ]);

        $this->get('/sirketler-ucun')->assertOk()
            ->assertSee('100+')
            ->assertSee('1.80 ₼')
            ->assertSee('300+')
            ->assertSee('1.55 ₼')
            // And the figures the page works the sum out from.
            ->assertSee('"from":100,"price":1.8', false)
            ->assertSee('id="co-quote"', false);
    }

    /** The owner's own notice carries the figure the page quoted. */
    public function test_the_telegram_notice_carries_the_sum(): void
    {
        $this->ladder([['from' => 100, 'price' => 1.80]]);
        \App\Support\Telegram::saveToken('123:abc');
        Setting::put(Setting::TELEGRAM_CHAT, '42');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->post('/sirketler-ucun', [
            'company' => 'Kafe Nar', 'phone' => '+994 55 555 55 55', 'quantity' => 250,
        ])->assertSessionHasNoErrors();

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, '250 ədəd')
                && str_contains($text, '1.80 ₼/əd')
                && str_contains($text, 'cəmi 450 ₼');
        });
    }

    /** The owner writes the steps in the admin. */
    public function test_the_owner_writes_the_steps_in_the_panel(): void
    {
        $admin = User::factory()->create(['role' => User::ADMIN]);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Filament\Pages\CorporateSettings::class)
            ->fillForm(['ladder' => [
                ['from' => 300, 'price' => 1.55],
                ['from' => 100, 'price' => 1.80],
            ]])
            ->call('save');

        $this->assertSame([
            ['from' => 100, 'price' => 1.80],
            ['from' => 300, 'price' => 1.55],
        ], CorporatePage::ladder());
    }
}
